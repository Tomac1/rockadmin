<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use DateTimeImmutable;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;

/**
 * Checks a submission against the same definitions that drew the form, so a
 * form can never accept something it did not offer.
 *
 * Two rules shape everything here. The first: validation produces a list and
 * never throws, and never stops at the first failure — a person filling in a
 * long form gets every problem at once, or they discover them one page reload
 * at a time. The second: a message names the field's *label*, because the
 * person reading it has never seen the column name.
 *
 * Only the fields `FormDefinition::editable()` returns are looked at. A
 * readonly or hidden field's value never comes from the wire, so there is
 * nothing here to check; its default is applied when the row is saved.
 */
final class FieldValidator
{
    /** The one shape a date control sends. */
    private const array DATE_FORMATS = ['Y-m-d'];

    /**
     * What a datetime control sends. A browser's `datetime-local` uses the
     * ISO `T` separator and omits the seconds unless the step asks for them;
     * a value redrawn from the database, or typed by hand, uses a space. All
     * four are accepted and all four leave as CANONICAL_DATETIME.
     */
    private const array DATETIME_FORMATS = [
        'Y-m-d\TH:i',
        'Y-m-d\TH:i:s',
        'Y-m-d H:i',
        'Y-m-d H:i:s',
    ];

    private const string CANONICAL_DATE = 'Y-m-d';

    private const string CANONICAL_DATETIME = 'Y-m-d H:i:s';

    /** @return list<ValidationError> empty when everything passed */
    public function validate(FormDefinition $form, Submission $submission): array
    {
        $errors = [];

        foreach ($form->editable() as $key => $field) {
            foreach ($this->check($field, $submission) as $message) {
                $errors[] = new ValidationError($key, $message);
            }
        }

        return $errors;
    }

    /**
     * The coerced values, safe to hand a WriteHandler. Call after validate().
     *
     * A field the submission did not carry is absent from the result, so that
     * an update writes only what was sent. The one exception is a checkbox:
     * an unchecked box sends nothing at all, and "nothing" there means false
     * rather than "leave it alone".
     *
     * @return array<string, mixed>
     */
    public function values(FormDefinition $form, Submission $submission): array
    {
        $values = [];

        foreach ($form->editable() as $key => $field) {
            if ($field->type === FieldType::Checkbox) {
                $values[$key] = $submission->has($key);

                continue;
            }

            if (!$submission->has($key)) {
                continue;
            }

            $values[$key] = $this->coerce($field, $submission->value($key));
        }

        return $values;
    }

    /** @return list<string> every reason this field was refused */
    private function check(FieldDefinition $field, Submission $submission): array
    {
        // A checkbox carries exactly one bit and the wire cannot get it
        // wrong: present is true, absent is false. Absent is therefore a
        // value, not a gap, which is why `required` has nothing to refuse
        // here — an unchecked box is a false the person chose.
        if ($field->type === FieldType::Checkbox) {
            return [];
        }

        $value = $submission->value($field->key);

        if ($this->isEmpty($value)) {
            return $field->required ? ["{$field->label} is required."] : [];
        }

        $messages = match ($field->type) {
            FieldType::Number => $this->checkNumber($field, $value),
            FieldType::Text, FieldType::Textarea, FieldType::Password => $this->checkLength($field, $value),
            FieldType::Select, FieldType::Radio => $this->checkOption($field, $value),
            FieldType::Multiselect => $this->checkOptions($field, $value),
            FieldType::Date, FieldType::Datetime => $this->checkDate($field, $value),
            // A field of type `hidden` that is not flagged `hidden` is a
            // value the form carries but does not show. It has no control to
            // constrain it, so only a declared pattern applies.
            FieldType::Hidden => [],
        };

        return [...$messages, ...$this->checkPattern($field, $value)];
    }

    /**
     * Empty is absent, the empty string, or an empty list. Nothing else:
     * `'0'` is a price, a count and an identifier, and `empty()` would throw
     * all three away.
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** @return list<string> */
    private function checkNumber(FieldDefinition $field, mixed $value): array
    {
        if (!\is_scalar($value) || !is_numeric($value)) {
            return ["{$field->label} must be a number."];
        }

        $number = (float) $value;
        $messages = [];

        if ($field->min !== null && $number < (float) $field->min) {
            $messages[] = "{$field->label} must be at least {$field->min}.";
        }

        if ($field->max !== null && $number > (float) $field->max) {
            $messages[] = "{$field->label} must be at most {$field->max}.";
        }

        return $messages;
    }

    /** @return list<string> */
    private function checkLength(FieldDefinition $field, mixed $value): array
    {
        if (!\is_scalar($value)) {
            return ["{$field->label} must be text."];
        }

        // mb_strlen, not strlen: 'ěščř' is four characters a person counted
        // and eight bytes nobody did.
        $length = mb_strlen($this->stringify($value));
        $messages = [];

        if ($field->min !== null && $length < $field->min) {
            $messages[] = "{$field->label} must be at least {$field->min} characters.";
        }

        if ($field->max !== null && $length > $field->max) {
            $messages[] = "{$field->label} must be at most {$field->max} characters.";
        }

        return $messages;
    }

    /** @return list<string> */
    private function checkOption(FieldDefinition $field, mixed $value): array
    {
        if (!\is_scalar($value) || !isset($field->options[$this->stringify($value)])) {
            return ["{$field->label} is not one of the available options."];
        }

        return [];
    }

    /** @return list<string> */
    private function checkOptions(FieldDefinition $field, mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return ["{$field->label} must be a list of options."];
        }

        foreach ($value as $entry) {
            if (!\is_scalar($entry) || !isset($field->options[$this->stringify($entry)])) {
                return ["{$field->label} is not one of the available options."];
            }
        }

        return [];
    }

    /** @return list<string> */
    private function checkDate(FieldDefinition $field, mixed $value): array
    {
        $isDate = $field->type === FieldType::Date;
        $noun = $isDate ? 'a valid date' : 'a valid date and time';

        if (!\is_string($value) || $this->parseDate($value, $isDate) === null) {
            return ["{$field->label} is not {$noun}."];
        }

        return [];
    }

    /** @return list<string> */
    private function checkPattern(FieldDefinition $field, mixed $value): array
    {
        if ($field->pattern === null || !\is_scalar($value)) {
            return [];
        }

        // The same delimiter PageRepository compiles the pattern with when it
        // refuses an invalid one at load, so a pattern that passed there is
        // the pattern that runs here.
        $matched = preg_match('~' . $field->pattern . '~', $this->stringify($value));

        // `false` is not `0`. PCRE returns it when it gave up — a backtrack
        // limit reached, or a subject it could not walk — and the load-time
        // check cannot foresee that, because it runs the pattern against an
        // empty string. The honest answer to the person filling in the form
        // is that their value did not pass, which is also the safe one: the
        // only way to reach `false` with a pattern that compiled is a value
        // crafted to defeat it.
        if ($matched === false || $matched === 0) {
            return ["{$field->label} is not in the required format."];
        }

        return [];
    }

    /**
     * Accepts a value only when it matches one of the shapes the field's own
     * control sends, and only when the parsed result formats back to exactly
     * what arrived.
     *
     * createFromFormat() is not a validator on its own. It accepts
     * '2026-02-31' and quietly hands back 3 March, and it accepts trailing
     * junk after a match. getLastErrors() exposes the first as a warning, and
     * the round trip through the same format catches both — this is the trap
     * CellFormatter was corrected for twice, handled the same way.
     */
    private function parseDate(string $value, bool $isDate): ?DateTimeImmutable
    {
        $formats = $isDate ? self::DATE_FORMATS : self::DATETIME_FORMATS;

        foreach ($formats as $format) {
            // '!' resets every field the format does not mention to the Unix
            // epoch, so a date-only value is midnight on that day rather
            // than "that day, but at whatever time it is now".
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($date === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            if ($date->format($format) !== $value) {
                continue;
            }

            return $date;
        }

        return null;
    }

    private function coerce(FieldDefinition $field, mixed $value): mixed
    {
        if ($this->isEmpty($value)) {
            // An empty control is an absent value, not the empty string: a
            // nullable column should end up NULL, and a NOT NULL one should
            // be refused by the database rather than filled with ''.
            return null;
        }

        return match ($field->type) {
            FieldType::Number => $this->coerceNumber($value),
            FieldType::Multiselect => $this->coerceList($value),
            FieldType::Date => $this->coerceDate($value, true),
            FieldType::Datetime => $this->coerceDate($value, false),
            FieldType::Checkbox => true,
            default => \is_scalar($value) ? $this->stringify($value) : $value,
        };
    }

    private function coerceNumber(mixed $value): mixed
    {
        if (!\is_scalar($value) || !is_numeric($value)) {
            return $value;
        }

        $text = $this->stringify($value);

        // An integral literal stays an integer, so an INT column is not
        // handed a float that the driver then writes as '7.0'.
        return preg_match('~^[+-]?\d+$~', $text) === 1 ? (int) $text : (float) $text;
    }

    /** @return list<string> */
    private function coerceList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            if (\is_scalar($entry)) {
                $entries[] = $this->stringify($entry);
            }
        }

        return $entries;
    }

    private function coerceDate(mixed $value, bool $isDate): mixed
    {
        if (!\is_string($value)) {
            return $value;
        }

        $date = $this->parseDate($value, $isDate);

        // A value validate() already refused passes through as it arrived,
        // so a caller that skipped validation gets the wrong value rather
        // than a plausible right-looking one.
        if ($date === null) {
            return $value;
        }

        return $date->format($isDate ? self::CANONICAL_DATE : self::CANONICAL_DATETIME);
    }

    private function stringify(bool|float|int|string $value): string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
