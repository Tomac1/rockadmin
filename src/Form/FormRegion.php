<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\Config\Placeholder;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Page as DbPage;
use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageException;

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
 * Fetching the row is `PreviewRegion`'s problem already solved: a
 * `RockAdmin\Db\Query` filtered on the entity's key and paged to one row,
 * handed to the same `RowSource` a grid reads from. No `QueryFactory` — that
 * builds a query out of a `GridState`, the filters, search, sort and page
 * number read out of a URL, and a form of one known row has no state at all.
 */
final class FormRegion
{
    public function __construct(
        private readonly RowSource $rows,
        private readonly UrlGenerator $urls,
        private readonly Csrf $csrf,
    ) {
    }

    /** A create form: every field at its default. */
    public function create(PageDefinition $page): FormView
    {
        $form = $this->form($page);

        return $this->view($page, $form, $this->defaults($form), true, null, []);
    }

    /** An edit form: null when no row has that key, so the caller 404s. */
    public function edit(PageDefinition $page, string $id): ?FormView
    {
        $form = $this->form($page);
        $row = $this->row($page, $form, $id);

        if ($row === null) {
            return null;
        }

        return $this->view($page, $form, $this->stored($form, $row, []), false, $id, []);
    }

    /**
     * A copy form: the row's values, except the fields `copy.reset` names,
     * which start at their own defaults again. It writes a new row, so it is
     * a create and carries no id — the row it was copied from is not the row
     * it will save over.
     */
    public function copy(PageDefinition $page, string $id): ?FormView
    {
        $form = $this->form($page);
        $row = $this->row($page, $form, $id);

        if ($row === null) {
            return null;
        }

        return $this->view($page, $form, $this->stored($form, $row, $form->resetOnCopy), true, null, []);
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
    public function reject(PageDefinition $page, Submission $submission, array $errors, ?string $id): FormView
    {
        $form = $this->form($page);
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

        return $this->view($page, $form, $values, $id === null, $id, $errors);
    }

    private function form(PageDefinition $page): FormDefinition
    {
        $region = $page->firstFormRegion();

        if ($region === null || $region->form === null) {
            throw new PageException("Page '{$page->name}' has no form region.");
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
        $key = $page->entity->key;

        // The entity's key is always selected, whether or not the form
        // declares a field for it, because the filter below matches against
        // it -- QueryBuilder drops a filter naming an alias the query never
        // selected, and a form that silently ignored its own id would edit
        // whichever row the database happened to return first.
        $columns = [$key => $key];

        foreach ($form->fields as $field) {
            $columns[$field->key] = $field->key;
        }

        $query = new Query(
            entity: $page->entity,
            columns: $columns,
            scope: $page->scope,
            filters: [new Filter($key, FilterOperator::Equals, $id)],
            page: DbPage::of(1, 1),
            count: CountStrategy::None,
        );

        $result = $this->fetch($query);

        return $result?->rows[0] ?? null;
    }

    /**
     * Runs the single-row query, reading a failed statement's own SQLSTATE to
     * tell "the id does not name a row" from "something is actually broken" —
     * the same reading `PreviewRegion` makes, for the same URL shape.
     *
     * `/p/ads/abc/edit` filters an integer key with a value that is not one.
     * MySQL coerces it and matches nothing; PostgreSQL refuses the statement
     * with SQLSTATE class '22', a data exception. Both are saying no such
     * row, so both answer 404. Anything else keeps propagating: a database
     * that is actually failing must not read as a row that does not exist.
     */
    private function fetch(Query $query): ?Result
    {
        try {
            return $this->rows->fetch($query);
        } catch (DbException $e) {
            if ($e->sqlStateClass() === '22') {
                return null;
            }

            throw $e;
        }
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
            returnTo: $this->urls->route('page.index', ['page' => $page->name]),
            isCreate: $isCreate,
            fields: $fields,
            errors: $formErrors,
            id: $id,
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
