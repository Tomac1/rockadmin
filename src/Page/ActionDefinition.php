<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\ActionReference;
use RockAdmin\Config\PageReference;

/**
 * One action of a page, built from its configuration.
 *
 * The sibling of `ColumnDefinition` and `FieldDefinition`, and like them a
 * plain value object: everything a page file may get wrong about an action is
 * refused in `PageRepository`, so nothing here needs checking again.
 *
 * `$to` is the reference object `Resolver` produced, not a URL and not the
 * text it was written as. Its `{column}` placeholders only mean anything once
 * there is a row, so turning it into a URL is the renderer's job.
 */
final class ActionDefinition
{
    /**
     * @param array<string, bool|float|int|string> $when    column => the one value it must hold for this
     *                                                       action to be offered on that row
     * @param array<string, bool|float|int|string> $set     column => the value a `post` writes to the row
     * @param list<string>                         $refresh region keys re-rendered once it completes
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ActionType $type,
        public readonly PageReference|ActionReference $to,
        public readonly string $icon = '',
        public readonly string $permission = '',
        public readonly string $confirm = '',
        public readonly array $when = [],
        public readonly array $set = [],
        public readonly array $refresh = [],
    ) {
    }

    /**
     * The verb this action posts to, or null when it does not post.
     *
     * `@action:archive` addresses the current page, so the verb is the whole
     * of the reference. It is read in three places — the gate asking what
     * permission the verb needs, the handler deciding which action a POST is,
     * and the builder minting the URL — and each unpacking the reference for
     * itself is three chances to disagree about what `@action:` means.
     */
    public function verb(): ?string
    {
        return $this->to instanceof ActionReference ? $this->to->action : null;
    }

    /**
     * Whether performing this means deleting the row rather than writing to
     * it. `@action:delete` reaches the verb the admin already has, so a page
     * gets a working delete button out of configuration alone and without a
     * `set` that would have to spell out what deletion means.
     */
    public function isDelete(): bool
    {
        return $this->verb() === 'delete';
    }
}
