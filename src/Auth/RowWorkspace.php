<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Db\RowReader;
use RockAdmin\Page\PageDefinition;

/**
 * Which workspace one row is in.
 *
 * Not the same question as "which workspace is active", and the difference is
 * the whole reason this exists. Under "all workspaces" nothing is active and
 * every row still belongs to somebody: a superadmin editing one ad of
 * Motobazar.cz must have Motobazar.cz active while that write runs, or the
 * project's own code -- which follows its own notion of the current tenant,
 * not RockAdmin's scope -- does its half of the work against the wrong one.
 *
 * **When a workspace is active, that is the answer and nothing is read.** The
 * scope is in the `WHERE` of every read and every write, so a row this request
 * can reach is a row in this workspace; asking the database to confirm it
 * would be a query to learn something already guaranteed.
 *
 * Only the other case costs a read, and it reads one column.
 */
final class RowWorkspace
{
    public function __construct(
        private readonly RequestScope $scope,
        private readonly RowReader $reader,
    ) {
    }

    /**
     * The workspace this write or this form belongs in, or null for "no
     * particular one".
     *
     * Null is a real answer in three cases that are all the same from here:
     * the page is not divided by workspace, none are configured, and a row
     * whose value no configured workspace claims. A host reads it as its own
     * default, which is what it would have done without any of this.
     *
     * @param ?string              $key    the row, or null for a create
     * @param array<string, mixed> $values a submission's values, consulted for a create -- which
     *                                     has no row to read the workspace out of yet
     */
    public function forRow(PageDefinition $page, ?string $key, array $values = []): ?WorkspaceDefinition
    {
        if (!$this->scope->isWorkspaceScoped($page)) {
            return null;
        }

        $active = $this->scope->activeWorkspace();

        if ($active !== null) {
            return $active;
        }

        $column = $this->scope->workspaceColumn($page);

        if ($column === null) {
            return null;
        }

        $value = $values[$column] ?? null;

        if ($value === null && $key !== null) {
            $row = $this->reader->byKey($page->entity, $key, [$column], $this->scope->forPage($page));
            $value = $row[$column] ?? null;
        }

        return $value === null ? null : $this->scope->workspaceFor($page, $value);
    }
}
