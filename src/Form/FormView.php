<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\View\Classes;

/**
 * One form, ready for a template: where it posts, what it carries, and what
 * is wrong with it.
 *
 * A create and an edit are the same object with a different `$action` and a
 * different `$id`, because they are the same form — the only honest
 * difference is whether a row already exists to write over.
 */
final class FormView
{
    /**
     * @param list<FormFieldView> $fields in the order the form declares them
     * @param list<string>        $errors form-level failures: a database integrity
     *                                     error, or a validation error naming no
     *                                     field this form draws
     */
    public function __construct(
        public readonly string $page,
        public readonly string $title,
        /** Where the form posts: the action route, never a hand-written URL. */
        public readonly string $action,
        public readonly string $token,
        /** Where to go once the write succeeds. */
        public readonly string $returnTo,
        public readonly bool $isCreate,
        public readonly array $fields,
        public readonly array $errors = [],
        /**
         * The row this form writes over, null for a create. It travels as a
         * hidden input because the action route names only the page and the
         * verb -- `POST /a/{page}/update` -- so the key has to arrive in the
         * body.
         */
        public readonly ?string $id = null,
        /**
         * Where a delete posts, null for a create -- there is nothing to
         * delete before the row exists. It is a separate address from
         * `$action` because the verb is in the URL (`POST /a/{page}/delete`),
         * so the template reaches it through the submit button's own
         * `formaction` rather than through a second, nested `<form>`, which
         * HTML does not allow.
         */
        public readonly ?string $deleteAction = null,
    ) {
    }

    public function isEdit(): bool
    {
        return !$this->isCreate;
    }

    public function hasErrors(): bool
    {
        if ($this->errors !== []) {
            return true;
        }

        foreach ($this->fields as $field) {
            if ($field->hasError()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every error on the form, field ones included, for the summary at the
     * top: a long form must not hide a failure below the fold.
     *
     * @return list<array{field: ?string, message: string}> `field` is the key to link to, null for a form-level error
     */
    public function allErrors(): array
    {
        $all = [];

        foreach ($this->errors as $message) {
            $all[] = ['field' => null, 'message' => $message];
        }

        foreach ($this->fields as $field) {
            foreach ($field->errors as $message) {
                $all[] = ['field' => $field->key, 'message' => $message];
            }
        }

        return $all;
    }

    public function classes(): string
    {
        return Classes::of('form', $this->page, [$this->isCreate ? 'ra-form-create' : 'ra-form-edit']);
    }
}
