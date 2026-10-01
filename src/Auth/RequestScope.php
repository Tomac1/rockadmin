<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Config\Binder;
use RockAdmin\Config\Placeholder;
use RockAdmin\Page\PageDefinition;

/**
 * The scope this request applies to a page, bound and ready for a WHERE.
 *
 * Four places need it — the grid's query, the single-row read behind a
 * preview and an action's `when`, the form that loads a row to edit, and
 * every write. Each of them working it out for itself is four chances to get
 * it subtly different, and the one that gets it wrong is a hole rather than a
 * bug: a scope missing from the write path is a cross-workspace write that
 * passes the gate and touches a row that exists.
 *
 * So it is one object, asked the same question by all four.
 *
 * **An empty scope is a legitimate answer and means "no boundary".** It is
 * what an admin with no `workspaces.php` gets, and what somebody working
 * across all workspaces gets. Both are deliberate, and neither is the same as
 * a scope that failed to bind — `Binder` raises for that rather than
 * returning nothing, because a boundary that quietly disappears is the whole
 * failure this subsystem exists to prevent.
 */
final class RequestScope
{
    public function __construct(
        private readonly ActiveWorkspace $active,
        private readonly Identity $identity,
    ) {
    }

    /**
     * The columns and values this request's rows must match on this page.
     *
     * @return array<string, mixed>
     */
    public function forPage(PageDefinition $page): array
    {
        if ($page->scope === []) {
            return [];
        }

        $user = $this->identity->user();
        $workspace = $this->active->current($user);

        if ($workspace === null && $this->workspacePlaceholdersIn($page) !== []) {
            // "All workspaces", or none configured. The workspace half of the
            // scope is dropped rather than bound -- that is what "all" means
            // -- while anything else in the scope stays, because a page
            // scoped to both a workspace and the signed-in user still belongs
            // to that user.
            return (new Binder(null, $user))->scope($this->withoutWorkspace($page));
        }

        return (new Binder($workspace, $user))->scope($page->scope);
    }

    /**
     * Whether this page's rows are divided by workspace at all.
     *
     * A page with no workspace placeholder in its scope is shared: the same
     * rows in every workspace. That is a real arrangement — a table of
     * countries, a list of categories — and it must not be turned into an
     * error by a reader who happens to be in no particular workspace.
     */
    public function isWorkspaceScoped(PageDefinition $page): bool
    {
        return $this->workspacePlaceholdersIn($page) !== [];
    }

    /**
     * The scope with its workspace placeholders removed.
     *
     * @return array<string, mixed>
     */
    private function withoutWorkspace(PageDefinition $page): array
    {
        $kept = $page->scope;

        foreach ($this->workspacePlaceholdersIn($page) as $column) {
            unset($kept[$column]);
        }

        return $kept;
    }

    /**
     * Which columns of this page's scope are bound from the workspace.
     *
     * @return list<string>
     */
    private function workspacePlaceholdersIn(PageDefinition $page): array
    {
        $columns = [];

        foreach ($page->scope as $column => $value) {
            if ($value instanceof Placeholder && $value->namespace === 'workspace') {
                $columns[] = (string) $column;
            }
        }

        return $columns;
    }
}
