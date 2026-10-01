<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Config\Placeholder;
use RockAdmin\Db\RowReader;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageException;
use RockAdmin\Page\RegionDefinition;

/**
 * Turns a described form, and a stored row when there is one, into the
 * `FormView` the form templates render.
 *
 * There is one builder here and four ways in. `create()` starts every field
 * at its default, `edit()` and `copy()` start it at the row's value, and
 * `reject()` redraws a refused submission — but all four end in the same
 * private `view()`, given the same shape: one value per field and whether
 * that value came from the row. A rejected form is therefore the same form,
 * by construction rather than by resemblance; two code paths drawing "the
 * same" control would agree until the day one of them was changed.
 *
 * Fetching the row is `RowReader`'s job and used to be this class's: a
 * `RockAdmin\Db\Query` filtered on the entity's key and paged to one row,
 * handed to the same `RowSource` a grid reads from. It moved out when
 * `ActionHandler` needed the same read to re-check an action's `when`
 * conditions — and specifically because of the SQLSTATE reading that goes
 * with it, which tells "that id names no row" from "the database is broken"
 * and which two copies of would drift silently. No `QueryFactory` — that
 * builds a query out of a `GridState`, the filters, search, sort and page
 * number read out of a URL, and a form of one known row has no state at all.
 *
 * **The delete button is the gate's answer, not this class's** (spec 9.2): it
 * is drawn only for somebody holding `{page}.delete`, asked of the same
 * `Gate::allows()` the kernel calls before `POST /a/{page}/delete` runs. The
 * form's own submit needs no such check — the kernel already refused anybody
 * without `{page}.create` or `{page}.update` before this form was drawn at
 * all, so a form that exists is a form its reader may save.
 */
final class FormRegion
{
    public function __construct(
        private readonly RowReader $reader,
        private readonly UrlGenerator $urls,
        private readonly Csrf $csrf,
        private readonly Gate $gate,
        /**
         * Who is asking, resolved at render time rather than at construction:
         * the region is wired once and the user belongs to the request. The
         * lookup is memoised inside `Identity`, so asking here costs no query
         * the kernel has not already paid for.
         */
        private readonly Identity $identity,
        /**
         * The workspace boundary this form reads inside. Optional for the
         * same reason it is everywhere else: an admin with no workspaces has
         * no boundary, and null answers [].
         */
        private readonly ?RequestScope $scope = null,
    ) {
    }

    /**
     * A create form: every field at its default.
     *
     * $returnTo is where the write goes back to — the grid page the person
     * came from, filters and all. Null falls back to the page's own index.
     */
    public function create(PageDefinition $page, RegionDefinition $region, ?string $returnTo = null): FormView
    {
        $form = $this->form($page, $region);

        return $this->view($page, $form, $this->defaults($form), true, null, [], $returnTo);
    }

    /** An edit form: null when no row has that key, so the caller 404s. */
    public function edit(
        PageDefinition $page,
        RegionDefinition $region,
        string $id,
        ?string $returnTo = null,
    ): ?FormView {
        $form = $this->form($page, $region);
        $row = $this->row($page, $form, $id);

        if ($row === null) {
            return null;
        }

        return $this->view($page, $form, $this->stored($form, $row, []), false, $id, [], $returnTo);
    }

    /**
     * A copy form: the row's values, except the fields `copy.reset` names,
     * which start at their own defaults again. It writes a new row, so it is
     * a create and carries no id — the row it was copied from is not the row
     * it will save over.
     */
    public function copy(
        PageDefinition $page,
        RegionDefinition $region,
        string $id,
        ?string $returnTo = null,
    ): ?FormView {
        $form = $this->form($page, $region);
        $row = $this->row($page, $form, $id);

        if ($row === null) {
            return null;
        }

        return $this->view($page, $form, $this->stored($form, $row, $form->resetOnCopy), true, null, [], $returnTo);
    }

    /**
     * Redrawing a rejected submission, with what was typed and why it failed.
     * $id is null for a create, the row's key for an edit.
     *
     * An edit starts from the row again rather than from the submission
     * alone, because a submission carries only the editable fields —
     * `Submission` drops the readonly and hidden ones on the way in — and a
     * redrawn form that lost them would not be the form that was submitted.
     * What the person typed is then laid over the top, for every editable
     * field, including the ones they emptied.
     *
     * @param list<ValidationError> $errors
     */
    public function reject(
        PageDefinition $page,
        RegionDefinition $region,
        Submission $submission,
        array $errors,
        ?string $id,
        ?string $returnTo = null,
    ): FormView {
        $form = $this->form($page, $region);
        $values = $this->defaults($form);

        if ($id !== null) {
            $row = $this->row($page, $form, $id);

            if ($row !== null) {
                $values = $this->stored($form, $row, []);
            }
        }

        foreach ($form->editable() as $key => $field) {
            $values[$key] = ['value' => $submission->value($key), 'stored' => false];
        }

        return $this->view($page, $form, $values, $id === null, $id, $errors, $returnTo);
    }

    /**
     * The region is named by the caller, the way `PreviewRegion` is told
     * which preview to render. A page may declare more than one form, and
     * milestone 8 will address one by `@region:` like anything else; a region
     * this class chose for itself would make that impossible to express.
     */
    private function form(PageDefinition $page, RegionDefinition $region): FormDefinition
    {
        if ($region->form === null) {
            throw new PageException(
                "Region '{$region->key}' of page '{$page->name}' is not a form.",
            );
        }

        return $region->form;
    }

    /**
     * Every field at its declared default, and none of them stored: a default
     * is what the row will start with, not what it already holds.
     *
     * @return array<string, array{value: mixed, stored: bool}>
     */
    private function defaults(FormDefinition $form): array
    {
        $values = [];

        foreach ($form->fields as $key => $field) {
            $values[$key] = ['value' => DefaultValue::for($field), 'stored' => false];
        }

        return $values;
    }

    /**
     * Every field at the row's value, except the ones named in $reset, which
     * fall back to their default and are therefore not stored values.
     *
     * @param  array<string, mixed>                            $row
     * @param  list<string>                                    $reset
     * @return array<string, array{value: mixed, stored: bool}>
     */
    private function stored(FormDefinition $form, array $row, array $reset): array
    {
        $values = [];

        foreach ($form->fields as $key => $field) {
            $values[$key] = \in_array($key, $reset, true)
                ? ['value' => DefaultValue::for($field), 'stored' => false]
                : ['value' => $row[$key] ?? null, 'stored' => true];
        }

        return $values;
    }

    /**
     * The one row this form writes over, or null when the key names none.
     *
     * @return array<string, mixed>|null
     */
    private function row(PageDefinition $page, FormDefinition $form, string $id): ?array
    {
        return $this->reader->byKey(
            $page->entity,
            $id,
            array_map(static fn ($field): string => $field->key, array_values($form->fields)),
            // Bound, not carried: `$page->scope` still holds Placeholder
            // objects. A form that loaded a row outside the workspace would
            // offer to edit it, and the write behind it would then be refused
            // -- a form that cannot save is worse than a row that is not
            // there, because it looks like a bug in the admin rather than a
            // row somebody may not touch.
            $this->scope?->forPage($page) ?? $page->scope,
        );
    }

    /**
     * The one place a `FormView` is built, whichever way in was taken.
     *
     * @param array<string, array{value: mixed, stored: bool}> $values
     * @param list<ValidationError>                            $errors
     */
    private function view(
        PageDefinition $page,
        FormDefinition $form,
        array $values,
        bool $isCreate,
        ?string $id,
        array $errors,
        ?string $returnTo,
    ): FormView {
        $messages = [];

        foreach ($errors as $error) {
            $messages[$error->field][] = $error->message;
        }

        $fields = [];

        foreach ($form->fields as $key => $field) {
            $value = $values[$key] ?? ['value' => null, 'stored' => false];

            // A field declared `hidden` has no control. It travels as an
            // <input type="hidden"> when its value came from the row, and
            // not at all when it came from its default: the default is
            // reapplied when the row is saved, so putting it in the page
            // would add nothing except something to tamper with.
            if ($field->hidden && !$value['stored']) {
                continue;
            }

            $fields[] = $this->fieldView($field, $value['value'], $messages[$key] ?? []);
            unset($messages[$key]);
        }

        // Whatever is left names a field this form does not draw — a hidden
        // one, or a key from somewhere else entirely. It is still a reason
        // the write failed, so it goes to the top of the form rather than
        // disappearing.
        $formErrors = [];

        foreach ($messages as $remaining) {
            foreach ($remaining as $message) {
                $formErrors[] = $message;
            }
        }

        return new FormView(
            page: $page->name,
            title: ($isCreate ? 'New ' : 'Edit ') . $page->title,
            action: $this->urls->route('action', [
                'page' => $page->name,
                'action' => $isCreate ? 'create' : 'update',
            ]),
            token: $this->csrf->token(),
            returnTo: $returnTo ?? $this->urls->route('page.index', ['page' => $page->name]),
            isCreate: $isCreate,
            fields: $fields,
            errors: $formErrors,
            id: $id,
            // Two reasons there may be no delete button, and they are not the
            // same reason: a create has no row to delete yet, and a person
            // without `{page}.delete` may not delete the row that does exist.
            // The second is the gate's answer, asked with the same
            // `Gate::allows()` the kernel asks before `POST /a/{page}/delete`
            // runs -- so the button is drawn exactly when pressing it would be
            // accepted (spec 9.2).
            deleteAction: $isCreate || !$this->gate->allows($this->identity->user(), $page->name . '.delete')
                ? null
                : $this->urls->route('action', [
                    'page' => $page->name,
                    'action' => 'delete',
                ]),
        );
    }

    /** @param list<string> $errors */
    private function fieldView(FieldDefinition $field, mixed $value, array $errors): FormFieldView
    {
        $type = $field->hidden ? FieldType::Hidden : $field->type;

        return new FormFieldView(
            key: $field->key,
            label: $field->label,
            type: $type,
            value: $this->control($type, $value),
            options: $type->takesOptions() ? $field->options : [],
            help: $field->help,
            placeholder: $field->placeholder,
            required: $field->required,
            readonly: $field->readonly,
            errors: $errors,
            min: $field->min,
            max: $field->max,
            step: $field->step,
            rows: $field->rows,
            pattern: $field->pattern,
        );
    }

    /**
     * The value as the control shows it, rather than as the database holds
     * it: a bool for a checkbox, a list of strings for a multiselect, a
     * string for everything else.
     *
     * A `Placeholder` shows as nothing. `{{workspace.site_id}}` is bound when
     * the row is written, and rendering the token into an input would offer
     * the person a literal `{{...}}` to edit, which is neither the value nor
     * a value they could correct.
     *
     * @return bool|string|list<string>
     */
    private function control(FieldType $type, mixed $value): bool|string|array
    {
        if ($type === FieldType::Checkbox) {
            return $value !== null
                && $value !== false
                && $value !== 0
                && $value !== '0'
                && $value !== ''
                && $value !== [];
        }

        if ($type->isMultiple()) {
            return $this->list($value);
        }

        if ($value === null || $value instanceof Placeholder || \is_array($value)) {
            return '';
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return \is_scalar($value) ? (string) $value : '';
    }

    /** @return list<string> */
    private function list(mixed $value): array
    {
        if (\is_scalar($value)) {
            return [(string) $value];
        }

        if (!\is_array($value)) {
            return [];
        }

        $entries = [];

        foreach ($value as $entry) {
            if (\is_scalar($entry)) {
                $entries[] = (string) $entry;
            }
        }

        return $entries;
    }
}
