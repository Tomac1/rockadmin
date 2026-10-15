<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\EnumOption;

/**
 * One field of a form, built from its configuration.
 *
 * A field is the form's equivalent of a `ColumnDefinition`: what it holds,
 * what control renders it, and the rules a submission must meet before it is
 * written. `$default` is read once, at load, and is whatever the page file
 * wrote after placeholder resolution — a scalar, a `Placeholder` object for
 * `{{workspace.*}}`/`{{user.*}}`, or one of the literal tokens `@now` and
 * `@uuid` — never stringified along the way.
 */
final class FieldDefinition
{
    /** @param array<string, EnumOption> $options */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly FieldType $type,
        public readonly mixed $default,
        public readonly bool $required,
        public readonly bool $readonly,
        public readonly bool $hidden,
        public readonly string $help,
        public readonly string $placeholder,
        public readonly array $options = [],
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $step = null,
        public readonly ?int $rows = null,
        public readonly ?string $pattern = null,
        /**
         * For a `file` field: the content types it takes, narrowing the
         * installation's own list.
         *
         * Empty means "whatever `uploads.types` allows". It can only narrow:
         * a page asking for a type the installation does not allow is refused
         * when the page loads, because the alternative is a field that offers
         * something and refuses it at the moment somebody uses it.
         *
         * @var list<string>
         */
        public readonly array $accept = [],
        /** For a `file` field: a smaller limit than `uploads.max_bytes`, never a larger one. */
        public readonly ?int $maxBytes = null,
    ) {
    }
}
