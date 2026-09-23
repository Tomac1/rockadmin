<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

/**
 * The schema for a page file.
 *
 * A page describes what an admin interface looks like: what data it shows,
 * where it comes from, how it is organized, and how a user can filter and
 * search it. The schema validates the page file against this shape.
 */
final class PageSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'title' => new SchemaKey(
                ValueType::String,
                required: true,
                description: 'The page title, shown in the browser tab and header.',
                example: 'Orders',
            ),
            'layout' => new SchemaKey(
                ValueType::String,
                default: 'single',
                description: 'The page layout: single, two-column or multi-column. Single is the default.',
                example: 'single',
            ),
            'entity' => new SchemaKey(
                ValueType::Array,
                description: 'The data source and entity being shown.',
                children: new Schema([
                    'table' => new SchemaKey(
                        ValueType::String,
                        required: true,
                        description: 'The database table holding the primary entity.',
                        example: 'orders',
                    ),
                    'key' => new SchemaKey(
                        ValueType::String,
                        default: 'id',
                        description: 'The primary key column. Defaults to `id`.',
                        example: 'id',
                    ),
                    'scope' => new SchemaKey(
                        ValueType::Array,
                        description: 'A SQL fragment to scope the entity. WHERE is added before it.',
                        children: new Schema([]),
                    ),
                    'relations' => new SchemaKey(
                        ValueType::Array,
                        description: 'Relationships to other tables, for joining.',
                        each: new Schema([]),
                    ),
                ]),
            ),
            'header' => new SchemaKey(
                ValueType::Array,
                description: 'Optional content shown at the top of the page.',
                children: new Schema([
                    'description' => new SchemaKey(
                        ValueType::String,
                        description: 'A short description of what this page shows.',
                        example: 'All orders, past and present.',
                    ),
                ]),
            ),
            'regions' => new SchemaKey(
                ValueType::Array,
                required: true,
                description: 'The regions of the page, each showing data in a different way.',
                each: new Schema([
                    'type' => new SchemaKey(
                        ValueType::String,
                        required: true,
                        description: 'The region type: list, form or custom.',
                        example: 'list',
                    ),
                    'per_page' => new SchemaKey(
                        ValueType::Int,
                        description: 'Rows to show per page. Only for list regions.',
                        example: 25,
                    ),
                    'sort' => new SchemaKey(
                        ValueType::Array,
                        description: 'Default sort order and options.',
                        children: new Schema([]),
                    ),
                    'search' => new SchemaKey(
                        ValueType::Array,
                        description: 'Search configuration.',
                        children: new Schema([]),
                    ),
                    'columns' => new SchemaKey(
                        ValueType::Array,
                        description: 'The columns shown in this region, each described by the column schema.',
                        each: ColumnSchema::create(),
                    ),
                ]),
            ),
        ]);
    }
}
