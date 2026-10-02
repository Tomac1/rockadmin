<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

/**
 * The schema for a form region's `form` block.
 *
 * A form is the fields it reads and writes, plus how copying a row differs
 * from editing one: `copy.reset` lists the fields that fall back to their
 * own default instead of being carried over from the source row.
 */
final class FormSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'fields' => new SchemaKey(
                ValueType::Array,
                required: true,
                description: 'The fields of this form, each described by the field schema.',
                each: FieldSchema::create(),
            ),
            'handler' => new SchemaKey(
                ValueType::Mixed,
                description: 'The project\'s own logic for saving this form, as a `@handler:` reference '
                    . 'naming something it registered: `@handler:save_ad`. It replaces what create, update '
                    . 'and delete do, not what surrounds them — validation, the permission, the workspace '
                    . 'scope, the transaction and the audit row all still happen, and the operation is told '
                    . 'which of the three it is serving. Declared here rather than only in wiring so that '
                    . 'this file does not tell half the truth about what saving does.',
                example: '@handler:save_ad',
            ),
            'copy' => new SchemaKey(
                ValueType::Array,
                description: 'How the copy action differs from a plain edit.',
                children: new Schema([
                    'reset' => new SchemaKey(
                        ValueType::Array,
                        description: 'Field keys that fall back to their own default on a copy, instead of '
                            . 'being carried over from the source row.',
                        example: ['state', 'published_at'],
                    ),
                ]),
            ),
        ]);
    }
}
