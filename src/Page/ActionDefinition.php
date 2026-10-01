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
        public readonly array $refresh = [],
    ) {
    }
}
