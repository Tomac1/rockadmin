<?php

declare(strict_types=1);

namespace RockAdmin\Form;

/**
 * One field, one reason it was refused.
 *
 * Validation produces a list of these rather than throwing, because a person
 * filling in a form deserves every problem at once. `$field` is the field's
 * key, so the view can attach the message to the right control; `$message` is
 * written for the person and names the field's label instead.
 */
final class ValidationError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
    ) {
    }
}
