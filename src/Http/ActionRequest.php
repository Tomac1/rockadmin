<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Page\ActionDefinition;
use RockAdmin\Page\PageDefinition;

/**
 * What a `POST /a/{page}/{verb}` turned out to be.
 *
 * `ActionHandler` used to answer that with one `in_array` against three
 * literals. A page may now declare verbs of its own, so the question has three
 * possible answers instead of one, and each of them needs different things out
 * of the request: a create reads a whole submission, an update reads one and a
 * row key, a configured action reads a row key and nothing else.
 *
 * It exists as a value rather than as a branch inside `handle()` because the
 * gate asks the same question a moment earlier, for its own reasons, and two
 * places deciding separately what `/a/ads/archive` means is how a request gets
 * authorised as one thing and performed as another.
 *
 * `$action` is null for the three built-in verbs, which no page declares and
 * nothing configures. A configured verb always carries its definition, so
 * nothing downstream looks it up a second time.
 */
final class ActionRequest
{
    private function __construct(
        public readonly PageDefinition $page,
        public readonly string $verb,
        public readonly ?ActionDefinition $action = null,
    ) {
    }

    /** A built-in verb: `create`, `update` or `delete`. */
    public static function builtIn(PageDefinition $page, string $verb): self
    {
        return new self($page, $verb);
    }

    /** A verb one of the page's own `post` actions declares. */
    public static function configured(PageDefinition $page, ActionDefinition $action): self
    {
        return new self($page, (string) $action->verb(), $action);
    }

    /**
     * The page's own `post` action for this verb, or null.
     *
     * Only the page's named `actions` block, never an action defined inline
     * in a column: an inline action exists in one place and is drawn there,
     * and giving it a URL anybody may post to would make "defined inline" a
     * way of adding a route without saying so. `Gate` reads the same block by
     * the same rule, and the two have to agree or a request is authorised as
     * one thing and performed as another.
     */
    public static function declaredBy(PageDefinition $page, string $verb): ?ActionDefinition
    {
        foreach ($page->actions as $action) {
            if ($action->type->isWrite() && $action->verb() === $verb) {
                return $action;
            }
        }

        return null;
    }

    /** Whether performing this means deleting the row rather than writing to it. */
    public function isDelete(): bool
    {
        return $this->verb === 'delete' || ($this->action?->isDelete() ?? false);
    }

    /** Whether it reads a submitted form rather than acting on a row as it stands. */
    public function isSubmission(): bool
    {
        return $this->action === null && ($this->verb === 'create' || $this->verb === 'update');
    }
}
