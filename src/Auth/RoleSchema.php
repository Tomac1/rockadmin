<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

/**
 * The schema for one entry of `roles.php`.
 *
 * The file's top level is a map of role key to one of these, the way
 * `enums.php` is a map of enumeration key to its options — so this schema
 * describes an entry rather than the file, and the reference's introduction
 * says what the keys of the file itself are.
 */
final class RoleSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'label' => new SchemaKey(
                ValueType::String,
                description: 'A human-readable name for the role, shown wherever roles are listed. '
                    . "Defaults to the role's own key.",
                example: 'Editor',
            ),
            'permissions' => new SchemaKey(
                ValueType::Array,
                required: true,
                description: 'What this role may do, as a list of `page.action` patterns. `*` stands for '
                    . 'every page or every action, and a leading `!` subtracts. Every subtraction is applied '
                    . 'after every widening, so the order the patterns are written in does not change what '
                    . 'the role means. The actions are `view`, `create`, `update` and `delete`; a page with '
                    . 'no form region has only `view`, so `!*` and a pattern naming a page or an action that '
                    . 'does not exist are refused at load rather than silently granting nothing.',
                example: ['ads.*', 'users.view', '!ads.delete'],
            ),
        ]);
    }
}
