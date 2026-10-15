<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\Http\UploadedFile;
use RockAdmin\Page\FormDefinition;

/**
 * What arrived in a POST body, narrowed to what the form is willing to hear.
 *
 * Three kinds of key are dropped, silently and on the way in: one the form
 * does not declare at all, one declared `readonly`, and one declared
 * `hidden`. All three arrive from a document anybody can edit in their
 * browser, so none of them is evidence of anything; a hidden field's value
 * comes from its default when the row is saved, never from the wire, which is
 * what stops a tampered form dropping a workspace scope. This is the same
 * discard rule the grid applies to unknown query parameters, and for the same
 * reason: what the configuration did not offer, the request cannot set.
 *
 * Nothing is coerced here. A submission carries the raw strings and arrays
 * PHP built from the body, so that a rejected form can be redrawn with
 * exactly what the person typed; turning them into values is
 * `FieldValidator::values()`.
 */
final class Submission
{
    /**
     * @param array<array-key, mixed>     $values array-key, not string: PHP normalises a numeric
     *                                            key such as '123' to the integer 123 on the way
     *                                            in, and no cast can undo it
     * @param array<string, UploadedFile> $files  by field key, already narrowed to this form's own
     */
    private function __construct(
        private readonly array $values,
        private readonly array $files = [],
    ) {
    }

    /**
     * @param array<array-key, mixed>     $body  the POST body, untrusted
     * @param array<string, UploadedFile> $files normally `$request->files`, equally untrusted
     */
    public static function fromBody(array $body, FormDefinition $form, array $files = []): self
    {
        $values = [];
        $uploads = [];

        foreach ($form->editable() as $key => $field) {
            if (\array_key_exists($key, $body)) {
                $values[$key] = $body[$key];
            }

            // Narrowed by the same rule the body is: a file posted under a
            // name this form does not declare is a file nobody asked for, and
            // storing one would mean a tampered form could fill a bucket.
            if ($field->type->isFile() && ($files[$key] ?? null)?->isPresent() === true) {
                $uploads[$key] = $files[$key];
            }
        }

        return new self($values, $uploads);
    }

    /** @return array<array-key, mixed> only keys the form declares as editable */
    public function values(): array
    {
        return $this->values;
    }

    public function value(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    /** The file chosen for this field, or null -- which on an edit means "leave the stored one". */
    public function file(string $field): ?UploadedFile
    {
        return $this->files[$field] ?? null;
    }

    /** @return array<string, UploadedFile> */
    public function files(): array
    {
        return $this->files;
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->values);
    }
}
