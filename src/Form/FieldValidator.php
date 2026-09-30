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
 * There is one entry point, and it hands back a `ValidationResult` rather
 * than a list plus a second method for the values. That is deliberate: the
 * two-call shape let a caller read values the validator had just refused,
 * and the only thing standing between them was a docblock.
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

    /**
     * What a `number` control can actually post: an optional sign, then
     * digits with at most one decimal point.
     *
     * is_numeric() is not this test. It accepts leading whitespace, which
     * then coerces to a float and quietly defeats the rule that an integral
     * literal stays an integer; it accepts hexadecimal; and it accepts
     * scientific notation, where '1e400' casts to INF and PDO cannot bind
     * it at all. None of those can be typed into a number control, so a
     * value carrying one did not come from the form this validates.
     */
    private const string NUMBER = '~^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$~D';

    /** An integral literal, for deciding whether a number stays an int. */
    private const string INTEGER = '~^[+-]?\d+$~D';

    public function validate(FormDefinition $form, Submission $submission): ValidationResult
    {
        $errors = [];
        $values = [];

        foreach ($form->editable() as $key => $field) {
            foreach ($this->check($field, $submission) as $message) {
                $errors[] = new ValidationError($field->key, $message);
            }

            // A checkbox is the one field whose absence is a value: an
            // unticked box sends nothing at all, and "nothing" there means
            // false rather than "leave this column alone".
            if ($field->type === FieldType::Checkbox) {
                $values[$key] = $submission->has($field->key);
            } elseif ($submission->has($field->key)) {
                $values[$key] = $this->coerce($field, $submission->value($field->key));
            }
        }

        return new ValidationResult($errors, $values);
    }

    /** @return list<string> every reason this field was refused */
    private function check(FieldDefinition $field, Submission $submission): array
    {
        // A checkbox carries exactly one bit and the wire cannot get it
        // wrong: present is true, absent is false. There is no such thing as
        // a malformed checkbox, so the only rule that can apply is
        // `required` — and it reads differently here than on every other
        // type. Elsewhere `required` means "this may not be left blank"; on
        // a checkbox it means "this must be ticked", because that is what it
        // means on every form anybody has ever filled in, and accepting the
        // terms is the reason the key gets written. An unchecked required
        // box is therefore a failure, not a false the person chose.
        if ($field->type === FieldType::Checkbox) {
            if ($field->required && !$submission->has($field->key)) {
                return ["{$field->label} must be ticked."];
            }

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
        if (!\is_scalar($value) || preg_match(self::NUMBER, $this->stringify($value)) !== 1) {
            return ["{$field->label} must be a number."];
        }

        $number = (float) $this->stringify($value);
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

        $matched = preg_match(self::compile($field->pattern), $this->stringify($value));

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
     * A field `pattern` as PCRE, anchored the way the HTML attribute it
     * mirrors is anchored.
     *
     * Three details, each of which was wrong once:
     *
     * - `^` and `$`, because an unanchored match constrains nothing. A
     *   pattern of `[A-Z]{2}\d{4}` would otherwise accept
     *   `"'; DROP TABLE users; -- AB1234"`, which makes the server-side
     *   check strictly weaker than the browser hint it is meant to enforce.
     * - `(?:...)` around the author's pattern, because anchoring `a|b` as
     *   `^a|b$` anchors only the first branch and leaves the second free to
     *   match anywhere.
     * - the `D` modifier, because `$` otherwise also matches immediately
     *   before a final newline, so even an author-anchored `^[A-Z]{2}\d{4}$`
     *   would admit `"AB1234\n"` and write it verbatim.
     *
     * `PageRepository` compiles a pattern exactly this way when it refuses
     * an invalid one at load. The two must not drift: a load-time check on a
     * different expression from the one that runs is not a check.
     */
    public static function compile(string $pattern): string
    {
        return '~^(?:' . $pattern . ')$~D';
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
        // A list column's absence is an empty list, not null. The null rule
        // below is about scalar columns, where '' and NULL are different
        // things and the empty control means the second.
        if ($field->type === FieldType::Multiselect) {
            return $this->coerceList($field, $value);
        }

        if ($this->isEmpty($value)) {
            // An empty control is an absent value, not the empty string: a
            // nullable column should end up NULL, and a NOT NULL one should
            // be refused by the database rather than filled with ''.
            return null;
        }

        return match ($field->type) {
            FieldType::Number => $this->coerceNumber($value),
            FieldType::Date => $this->coerceDate($value, true),
            FieldType::Datetime => $this->coerceDate($value, false),
            FieldType::Checkbox => true,
            default => \is_scalar($value) ? $this->stringify($value) : $value,
        };
    }

    private function coerceNumber(mixed $value): mixed
    {
        if (!\is_scalar($value)) {
            return $value;
        }

        $text = $this->stringify($value);

        // Anything validate() refused travels as it arrived, so a caller
        // that somehow skipped validation gets a visibly wrong value rather
        // than a plausible one. The date path has the same instinct.
        if (preg_match(self::NUMBER, $text) !== 1) {
            return $value;
        }

        if (preg_match(self::INTEGER, $text) !== 1) {
            return (float) $text;
        }

        // An integral literal stays an integer, so an INT column is not
        // handed a float the driver then writes as '7.0'. filter_var is what
        // decides, because it refuses what (int) would silently mangle: a
        // literal wider than PHP's int range comes back false, and casting
        // it — to int or to float — would lose digits a BIGINT column can
        // hold. Such a literal travels as its own text and the column
        // decides, which is the honest answer rather than a rounded one.
        $integer = filter_var($text, FILTER_VALIDATE_INT);

        return $integer === false ? $text : $integer;
    }

    /**
     * @return list<string> only entries the field actually offers
     */
    private function coerceList(FieldDefinition $field, mixed $value): array
    {
        // Defence in depth. `ValidationResult` already makes it impossible
        // to read the values of a submission this would have had to repair,
        // so nothing here should ever fire — but an earlier shape of this
        // method turned an associative array into a list and let an
        // undeclared entry through, which is the kind of quiet repair that
        // makes a refusal stop meaning anything. Dropping what was not
        // offered is cheaper than trusting that the guard upstream is never
        // removed.
        if (!\is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            if (\is_scalar($entry) && isset($field->options[$this->stringify($entry)])) {
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
