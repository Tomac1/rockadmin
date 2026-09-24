<?php

declare(strict_types=1);

namespace RockAdmin\Form;

/**
 * The body keys a form carries that are not columns of the entity.
 *
 * A template writes these and an action handler reads them back, which makes
 * them a contract between two pieces of code that never call each other.
 * `Csrf::FIELD` already exists for exactly that reason; these are its
 * siblings, and without them each side would spell the string from memory.
 *
 * The leading underscore keeps them clear of a column genuinely named `id` or
 * `ret`. A field key may not begin with one, so a submission can never carry
 * a value that lands on the same name.
 */
final class FormFields
{
    /** The key of the row being written, for a form that edits or copies one. */
    public const ID = '_id';

    /** Where to send the person once the write succeeds. */
    public const RETURN_TO = '_ret';

    /** @return list<string> every reserved key, for a refusal that names them all */
    public static function reserved(): array
    {
        return [self::ID, self::RETURN_TO];
    }
}
