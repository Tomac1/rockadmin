<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\View\Classes;

/**
 * One row, rendered as a field list: ready for a template, carrying nothing
 * a template could work backwards into a database or a configuration object.
 */
final class PreviewView
{
    /** @param list<FieldView> $fields in the order the region declares them */
    public function __construct(
        public readonly string $key,
        /** The row's own label -- e.g. an ad's title -- never the page's. */
        public readonly string $title,
        public readonly string $id,
        public readonly array $fields,
    ) {
    }

    public function classes(): string
    {
        return Classes::of('preview', $this->key);
    }
}
