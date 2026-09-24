<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

/**
 * The schema for a column in a region.
 *
 * A column describes a value to display: its type, how it looks, where it
 * comes from, and whether it is sortable or searchable. Related data (a
 * one-to-many) is fetched separately and declared in the `collection` block.
 */
final class ColumnSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'type' => new SchemaKey(
                ValueType::String,
                default: 'text',
                description: 'What the column holds: text, int, money, datetime, bool, enum or json.',
                example: 'text',
            ),
            'label' => new SchemaKey(
                ValueType::String,
                description: 'The column header, shown above the values. Defaults at load to the key, title-cased.',
                example: 'First Name',
            ),
            'source' => new SchemaKey(
                ValueType::String,
                description: 'A source path: the database column or expression to read. '
                    . 'Defaults to the key. Use `.` to join a relation, `->` to traverse JSON. '
                    . 'Must not be set on a column carrying `collection`.',
                example: 'user.name',
            ),
            'display' => new SchemaKey(
                ValueType::String,
                description: 'How the column looks: plain, badge, check, yesno, progress or percent. '
                    . 'Defaults to the type\'s own. Not every type allows every display. A cell links to '
                    . 'the row\'s detail page independently of this, via the `link` key.',
                example: 'badge',
            ),
            'sortable' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Whether the column header is a sort link.',
                example: true,
            ),
            'searchable' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Whether the region\'s search box looks here.',
                example: true,
            ),
            'align' => new SchemaKey(
                ValueType::String,
                description: 'Horizontal alignment: start or end. Defaults to the type\'s own. '
                    . 'Numbers align to the end so they are readable when skimmed.',
                example: 'end',
            ),
            'width' => new SchemaKey(
                ValueType::String,
                description: 'A CSS width for the column, e.g. `8rem`. Without it, the column shares space equally.',
                example: '8rem',
            ),
            'class' => new SchemaKey(
                ValueType::String,
                description: 'Extra CSS classes added to every cell in this column.',
                example: 'font-mono',
            ),
            'link' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Makes the cell an anchor to the row\'s detail page.',
                example: true,
            ),
            'currency' => new SchemaKey(
                ValueType::String,
                description: 'ISO 4217 currency code. Shown after the amount. For `money` type only.',
                example: 'USD',
            ),
            'format' => new SchemaKey(
                ValueType::String,
                description: 'PHP date format string. For `datetime` type only.',
                example: 'Y-m-d H:i',
            ),
            'max' => new SchemaKey(
                ValueType::Int,
                description: 'The value that counts as full. For `progress` display only.',
                example: 100,
            ),
            'options' => new SchemaKey(
                ValueType::Mixed,
                description: 'The enum values, keyed by their stored value: an `@enum:` reference to a shared '
                    . 'enumeration, or a literal map where each entry is either the label as a plain string, '
                    . 'or `[\'label\' => ..., \'color\' => ...]` when the value needs a badge colour. '
                    . 'For `enum` type only.',
                example: ['active' => ['label' => 'Active', 'color' => 'success'], 'inactive' => 'Inactive'],
            ),
            'filter' => new SchemaKey(
                ValueType::Array,
                description: 'Makes the column filterable. Declares how the filter works.',
                children: new Schema([
                    'type' => new SchemaKey(
                        ValueType::String,
                        default: 'text',
                        description: 'The filter type: text, select, multiselect, range, date or boolean.',
                        example: 'text',
                    ),
                    'op' => new SchemaKey(
                        ValueType::String,
                        description: 'The filter operator: equals, not_equals, contains, starts_with, '
                            . 'ends_with, gt, gte, lt, lte, between, in, is_null or is_not_null. '
                            . 'Defaults per filter type: text uses `contains`, select and boolean use `equals`, '
                            . 'multiselect uses `in`, range and date use `between`.',
                        example: 'contains',
                    ),
                    'label' => new SchemaKey(
                        ValueType::String,
                        description: 'The filter label, shown beside the input. Defaults to the column\'s label.',
                        example: 'Search by name',
                    ),
                    'options' => new SchemaKey(
                        ValueType::Mixed,
                        description: 'For select and multiselect: the same shapes the column\'s own `options` '
                            . 'accepts — an `@enum:` reference, or a literal map from value to either a label '
                            . 'string or `[\'label\' => ..., \'color\' => ...]`. '
                            . 'Defaults to the column\'s own options when it is an enum.',
                        example: ['active' => ['label' => 'Active', 'color' => 'success'], 'inactive' => 'Inactive'],
                    ),
                    'placeholder' => new SchemaKey(
                        ValueType::String,
                        description: 'Placeholder text shown in an empty text filter.',
                        example: 'Type a name...',
                    ),
                ]),
            ),
            'collection' => new SchemaKey(
                ValueType::Array,
                description: 'Fetches a one-to-many relationship. The column displays collected values; '
                    . 'a supplementary query fetches all of them for the whole page.',
                children: new Schema([
                    'table' => new SchemaKey(
                        ValueType::String,
                        required: true,
                        description: 'The table holding the many-side records.',
                        example: 'order_items',
                    ),
                    'foreign_key' => new SchemaKey(
                        ValueType::String,
                        required: true,
                        description: 'The column in the collection table that points back to this entity\'s key.',
                        example: 'order_id',
                    ),
                    'column' => new SchemaKey(
                        ValueType::String,
                        required: true,
                        description: 'The column in the collection table to collect, one value per related row.',
                        example: 'sku',
                    ),
                ]),
            ),
        ]);
    }
}
