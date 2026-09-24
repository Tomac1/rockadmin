<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\Config\EnumOption;
use RockAdmin\Page\FieldType;
use RockAdmin\View\Classes;

/**
 * One control of a form: what it is called, what it currently holds, and why
 * it was refused.
 *
 * Everything here is a value — a string, a bool, an enum case, an
 * `EnumOption` — never a `FieldDefinition`. A template that could reach the
 * definition could reach the page, the form and eventually the database,
 * which is the two-levels-down reach rule 4 of this project forbids.
 *
 * `$value` is the value as the control should show it, not as the database
 * holds it: a string for every text-like control, a bool for a checkbox, a
 * list of strings for a multiselect. That is what lets a rejected submission
 * be redrawn with exactly what was typed, since what was typed is already a
 * string.
 */
final class FormFieldView
{
    /**
     * @param array<string, EnumOption> $options  keyed by value, empty for a type that takes none
     * @param list<string>              $errors   every reason this field was refused, in the order
     *                                             validation found them
     * @param bool|string|list<string>  $value
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly FieldType $type,
        public readonly bool|string|array $value,
        public readonly array $options = [],
        public readonly string $help = '',
        public readonly string $placeholder = '',
        public readonly bool $required = false,
        public readonly bool $readonly = false,
        public readonly array $errors = [],
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $step = null,
        public readonly ?int $rows = null,
        public readonly ?string $pattern = null,
    ) {
    }

    /**
     * The message to show against the control. A field can fail more than one
     * rule at once — a number below its minimum and outside its pattern — and
     * `$errors` keeps all of them for the summary at the top of the form; the
     * control itself has room for one.
     */
    public function error(): ?string
    {
        return $this->errors[0] ?? null;
    }

    public function hasError(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Structural, identity and type classes — and nothing that depends on the
     * value or on whether this field was refused, so that a redrawn form is
     * styled by the same selectors as a fresh one. A template reads
     * `hasError()` for the invalid state, which is what `aria-invalid` needs
     * anyway.
     */
    public function classes(): string
    {
        return Classes::of('form-field', $this->key, ['ra-form-field-' . $this->type->value]);
    }
}
