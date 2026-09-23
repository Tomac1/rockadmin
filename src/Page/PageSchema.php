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
                        description: 'A map of column to value, most often a placeholder. Every entry becomes '
                            . 'a filter that is always applied and can never be removed by a URL, which is what '
                            . 'makes it a workspace boundary rather than a default.',
                        example: ['site_id' => '{{workspace.site_id}}'],
                    ),
                    'relations' => new SchemaKey(
                        ValueType::Array,
                        description: 'Relationships to other tables, for joining.',
                        each: new Schema([
                            'table' => new SchemaKey(
                                ValueType::String,
                                required: true,
                                description: 'The table this relation joins.',
                                example: 'users',
                            ),
                            'on' => new SchemaKey(
                                ValueType::String,
                                required: true,
                                description: 'The join condition, written with table names, e.g. '
                                    . '`users.id = ads.user_id`.',
                                example: 'users.id = ads.user_id',
                            ),
                            'type' => new SchemaKey(
                                ValueType::String,
                                default: 'left',
                                description: 'The join type: left or inner.',
                                example: 'left',
                            ),
                        ]),
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
                        description: 'The region type: list or preview. Refused at load if it names anything '
                            . 'else. `form`, `nav` and `stat` arrive in later milestones.',
                        example: 'list',
                    ),
                    'per_page' => new SchemaKey(
                        ValueType::Int,
                        description: 'Rows to show per page. Only for list regions.',
                        example: 25,
                    ),
                    'sort' => new SchemaKey(
                        ValueType::Array,
                        description: 'The default sort order: a map of column key to direction, applied '
                            . 'until a user picks their own.',
                        example: ['created_at' => 'desc'],
                    ),
                    'search' => new SchemaKey(
                        ValueType::Array,
                        description: 'Presentation for the region\'s search box. Which columns it searches '
                            . 'is decided per column, by that column\'s own `searchable` key.',
                        children: new Schema([
                            'placeholder' => new SchemaKey(
                                ValueType::String,
                                description: 'Placeholder text shown in the empty search box.',
                                example: 'Search...',
                            ),
                        ]),
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
