<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

/**
 * The schema for a field in a form region.
 *
 * A field describes one value a form reads and writes: its type, its
 * control, what a new row starts with, and the rules a submission must meet
 * before it is written. Validation is server-side and reuses this same
 * definition — there is no second copy of the rules in a template.
 */
final class FieldSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'type' => new SchemaKey(
                ValueType::String,
                default: 'text',
                description: 'What the field holds and which control it renders: text, textarea, number, '
                    . 'select, multiselect, checkbox, radio, date, datetime, hidden or password.',
                example: 'text',
            ),
            'label' => new SchemaKey(
                ValueType::String,
                description: 'The label shown beside the control. Defaults at load to the key, first letter '
                    . 'upper-cased, underscores turned to spaces.',
                example: 'First Name',
            ),
            'default' => new SchemaKey(
                ValueType::Mixed,
                description: 'What a new row starts with: a literal, a `{{placeholder}}`, or one of the '
                    . 'tokens `@now` and `@uuid`. Applies only when a row is created — an existing row is '
                    . 'never touched by it.',
                example: 'draft',
            ),
            'required' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Whether an empty value is refused on submission.',
                example: true,
            ),
            'readonly' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Renders the control disabled, and the field is never read from a submission — '
                    . 'its value on save always comes from its default or from the existing row.',
                example: true,
            ),
            'hidden' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Renders no control at all. Its default is still applied on save, so a hidden '
                    . 'field is how a form carries a value the user never sees or edits, such as a workspace '
                    . 'scope.',
                example: true,
            ),
            'help' => new SchemaKey(
                ValueType::String,
                description: 'Help text shown under the control.',
                example: 'Shown to customers on the storefront.',
            ),
            'placeholder' => new SchemaKey(
                ValueType::String,
                description: 'Placeholder text shown inside an empty text-like control.',
                example: 'Enter a title...',
            ),
            'options' => new SchemaKey(
                ValueType::Mixed,
                description: 'The choices offered: an `@enum:` reference to a shared enumeration, or a '
                    . 'literal map from stored value to label. For `select`, `multiselect` and `radio` only.',
                example: ['active' => 'Active', 'inactive' => 'Inactive'],
            ),
            'min' => new SchemaKey(
                ValueType::Int,
                description: 'For a `number` field, the smallest accepted value. For a text-like field, the '
                    . 'shortest accepted length.',
                example: 0,
            ),
            'max' => new SchemaKey(
                ValueType::Int,
                description: 'For a `number` field, the largest accepted value. For a text-like field, the '
                    . 'longest accepted length.',
                example: 100,
            ),
            'step' => new SchemaKey(
                ValueType::String,
                description: 'The HTML step attribute for a `number` field, e.g. `0.01` to allow cents.',
                example: '0.01',
            ),
            'rows' => new SchemaKey(
                ValueType::Int,
                description: 'How many rows tall a `textarea` control is.',
                example: 4,
            ),
            'pattern' => new SchemaKey(
                ValueType::String,
                description: 'A regular expression the value must match, written without delimiters.',
                example: '[A-Z]{2}\\d{4}',
            ),
        ]);
    }
}
