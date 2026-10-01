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
        /**
         * The configured workspaces, for naming which one a row is in when a
         * grid is showing all of them. Optional, because binding a scope
         * needs only the active one -- it is the "all workspaces" column
         * that needs the catalogue.
         */
        private readonly ?Workspaces $workspaces = null,
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
     * Whether this request is reading every workspace at once.
     *
     * Specification 9.3: "the scope is omitted and the grid automatically
     * adds a workspace column so rows remain attributable". This is the
     * condition for that column — a page with no workspace in its scope
     * never needs one, because its rows are not divided.
     */
    public function isAcrossAllWorkspaces(PageDefinition $page): bool
    {
        return $this->isWorkspaceScoped($page)
            && $this->active->current($this->identity->user()) === null;
    }

    /**
     * Which column of this page says which workspace a row is in.
     *
     * The scope's own column, because it is the only one known to identify
     * the workspace: whatever a page scopes on is by definition what divides
     * its rows. Inventing a second convention — a `workspace_column` key, a
     * guess at `tenant_id` — would be a second thing to keep in agreement
     * with the first.
     *
     * The first, when a page scopes on several. A grid explaining itself with
     * one column is the useful case; a page dividing its rows on two is
     * welcome to, and the column names the one that comes first.
     */
    public function workspaceColumn(PageDefinition $page): ?string
    {
        return $this->workspacePlaceholdersIn($page)[0] ?? null;
    }

    /**
     * Which workspace a row's value belongs to, by label.
     *
     * Matched as text, the way an action's `when` is, because one driver
     * hands back `1` where another hands back `'1'` — and a grid that said
     * "2" on PostgreSQL and "Motobazar.cz" on MySQL would be a rule-3 bug
     * nobody would think to look for.
     *
     * A value no workspace claims comes back as itself rather than as
     * nothing. Rows can outlive the workspace they were written in, and
     * "tenant 7" is more use to whoever is looking at it than an empty cell.
     */
    public function workspaceLabel(PageDefinition $page, mixed $value): string
    {
        $column = $this->workspaceColumn($page);
        $placeholder = $column === null ? null : $page->scope[$column] ?? null;
        $text = \is_scalar($value) ? (string) $value : '';

        if (!$placeholder instanceof Placeholder) {
            return $text;
        }

        foreach ($this->workspaces?->all() ?? [] as $workspace) {
            $theirs = $workspace->var($placeholder->name);

            if ($theirs !== null && (string) $theirs === $text) {
                return $workspace->label;
            }
        }

        return $text;
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
