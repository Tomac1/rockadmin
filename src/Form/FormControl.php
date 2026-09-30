<?php

declare(strict_types=1);

namespace RockAdmin\Form;

/**
 * The identity and the shared attributes of one form control.
 *
 * `CellPartial` exists for the same reason one layer over: a rule that two
 * templates both need, written once rather than twice. Here it is three
 * rules, and eleven control templates need all three.
 *
 * - **Which template draws the control** — `FieldType::template()` already
 *   names it, and this only puts it under `region/form/`, so the filename is
 *   still the type and a project restyling every date input knows which file
 *   to copy without reading any source.
 * - **What the control's `id` is** — `field.php` writes a `<label for>` and
 *   `errors.php` writes a `#fragment` link to the very same control, and
 *   neither of them draws it. Milestone 6 shipped a range filter whose label
 *   pointed at nothing, which is what two hand-written id spellings look
 *   like once one of them changes.
 * - **The attributes every control carries** — name, id, required, readonly,
 *   `aria-invalid` and `aria-describedby`. Repeating the `aria-describedby`
 *   assembly in eleven files would mean eleven chances to forget the error
 *   half of it, and a control that is `aria-invalid` while describing only
 *   its help text tells a screen reader that something is wrong and then
 *   refuses to say what.
 *
 * A static class rather than more properties on `FormFieldView`, because
 * none of this is part of what a form *is*: an id prefix is a rendering
 * convention, and a project that overrides every template may want its own.
 */
final class FormControl
{
    /** The control itself: what a `<label for>` and a summary link point at. */
    public static function id(FormFieldView $field): string
    {
        return self::idFor($field->key);
    }

    /**
     * The same id from a field's key alone. `errors.php` has only the key —
     * `FormView::allErrors()` carries one per entry, not a whole field view —
     * and the summary's link has to reach the very same element the label
     * does.
     */
    public static function idFor(string $key): string
    {
        return 'ra-form-input-' . $key;
    }

    /**
     * One option of a `radio` group, which has no single control to label:
     * each option is its own input with its own label, and the group's label
     * points at the first of them.
     */
    public static function optionId(FormFieldView $field, int $index): string
    {
        return self::id($field) . '-' . $index;
    }

    /**
     * The field's own label, as an element id. A `radio` field has no single
     * control for a `<label for>` to point at — each option is its own input
     * with its own label — so the group is labelled by reference instead,
     * which is the same relationship written the other way round.
     */
    public static function labelId(FormFieldView $field): string
    {
        return 'ra-form-label-' . $field->key;
    }

    public static function errorId(FormFieldView $field): string
    {
        return 'ra-form-error-' . $field->key;
    }

    public static function helpId(FormFieldView $field): string
    {
        return 'ra-form-help-' . $field->key;
    }

    /**
     * What the control posts under. A multiselect sends several values, so
     * its name carries the brackets PHP reads back as a list.
     */
    public static function name(FormFieldView $field): string
    {
        return $field->type->isMultiple() ? $field->key . '[]' : $field->key;
    }

    /**
     * The ids describing this control: its error first, because that is what
     * the person needs to hear before its help text, and both only when they
     * are actually rendered. Null when there is nothing to describe, so
     * `$attrs()` leaves the attribute out rather than writing an empty one
     * that names no element.
     */
    public static function describedBy(FormFieldView $field): ?string
    {
        $ids = [];

        if ($field->hasError()) {
            $ids[] = self::errorId($field);
        }

        if ($field->help !== '') {
            $ids[] = self::helpId($field);
        }

        return $ids === [] ? null : implode(' ', $ids);
    }

    /**
     * The attributes shared by every control, ready for the `$attrs()`
     * helper: true writes a bare attribute, null omits it entirely.
     *
     * `$disableWhenReadonly` is the one honest difference between the
     * controls. `readonly` keeps a text input's value selectable and still
     * submits it; a `<select>`, a checkbox and a radio have no `readonly` at
     * all, so those have to be `disabled` instead. Either way the value is
     * not read back from the wire — `Submission` drops a readonly field —
     * so the difference is entirely about what the person can see and do.
     *
     * @return array<string, scalar|null>
     */
    public static function attributes(FormFieldView $field, bool $disableWhenReadonly = false): array
    {
        return [
            'id' => self::id($field),
            'name' => self::name($field),
            'required' => $field->required,
            'readonly' => !$disableWhenReadonly && $field->readonly,
            'disabled' => $disableWhenReadonly && $field->readonly,
            'aria-invalid' => $field->hasError() ? 'true' : null,
            'aria-describedby' => self::describedBy($field),
        ];
    }

    /** The template that draws this field's control. */
    public static function templateFor(FormFieldView $field): string
    {
        return 'region/form/' . $field->type->template();
    }
}
