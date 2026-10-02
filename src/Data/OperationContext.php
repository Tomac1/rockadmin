<?php

declare(strict_types=1);

namespace RockAdmin\Data;

use Closure;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\WorkspaceDefinition;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;
use RockAdmin\Page\PageDefinition;

/**
 * What an operation is told, and the one thing it may ask for.
 *
 * Everything here is already decided. The gate has passed, the `when`
 * conditions have been re-checked against the row as it actually is, the
 * scope is bound, the transaction is open and the host has been asked to
 * switch into this row's workspace. An operation reading this is reading
 * answers, not questions — the same contract a view object keeps with a
 * template, one layer over.
 */
final class OperationContext
{
    /** @var list<Closure():void> */
    private array $afterCommit = [];

    /**
     * @param string               $operation `create`, `update`, `delete`, or the page's own verb
     * @param ?string              $key       the row being written, null for a create
     * @param array<string, mixed> $scope     the workspace boundary, bound and ready for a WHERE
     */
    public function __construct(
        public readonly PageDefinition $page,
        public readonly string $operation,
        public readonly ?string $key,
        public readonly array $scope,
        /**
         * The workspace this row is in — *its* workspace, not the one the
         * reader happens to be looking at.
         *
         * Under "all workspaces" there is no active workspace and every row
         * still belongs to one. A superadmin editing one ad gets that ad's
         * tenant without switching first, which is why this is resolved from
         * the row rather than from the session.
         *
         * Null when the page is not divided by workspace at all, or when none
         * are configured. By the time an operation sees this the host has
         * already been asked to switch into it, so it is here to be read
         * rather than acted on.
         */
        public readonly ?WorkspaceDefinition $workspace,
        /** Who pressed the button. */
        public readonly ?AuthUser $actor,
    ) {
    }

    /**
     * Queues something to run once the write is real.
     *
     * A transaction can take back a row and cannot take back an email. An
     * operation that sends mail inline and then fails has told somebody about
     * a change that did not happen, and nothing anywhere says so — which is
     * the quiet kind of wrong. So the write goes inside the transaction and
     * the side effect goes here.
     *
     * Nothing queued runs if the operation raises, if a later row of a bulk
     * action raises, or if the commit itself fails. That is the whole point:
     * these run when the change is true and not before.
     *
     * @param Closure():void $work
     */
    public function afterCommit(Closure $work): void
    {
        $this->afterCommit[] = $work;
    }

    /**
     * Everything queued, for the one caller that runs it.
     *
     * `ActionHandler` calls this after the transaction commits. It is not
     * part of what an operation does and is public only because the two live
     * in different namespaces.
     *
     * @return list<Closure():void>
     */
    public function queued(): array
    {
        return $this->afterCommit;
    }

    /**
     * The write RockAdmin would have done.
     *
     * Most operations want the ordinary write and something else — an email,
     * a cache drop, a row in another table. Rewriting the INSERT by hand to
     * get there would be a second spelling of the default, and the first
     * thing to drift.
     *
     * @param array<string, mixed> $values
     */
    public function writeDefault(WriteHandler $writes, array $values): ?WriteResult
    {
        return match ($this->operation) {
            'create' => $writes->insert($this->page->entity, $values),
            'delete' => $writes->delete($this->page->entity, (string) $this->key, $this->scope),
            default => $values === []
                ? null
                : $writes->update($this->page->entity, (string) $this->key, $values, $this->scope),
        };
    }
}
