<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\Http\UploadedFile;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FormDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Storage\FileTypes;
use RockAdmin\Storage\Storage;
use RockAdmin\Storage\StorageException;

/**
 * What happens to a file between a submission and a row.
 *
 * Four questions a form save has to answer for every `file` field, and only
 * the first is obvious:
 *
 * 1. a new file was uploaded — store it, and put its key in the column;
 * 2. nothing was uploaded — leave the column alone, because an empty file
 *    input means "keep what is there";
 * 3. the remove box was ticked — clear the column;
 * 4. and whichever of those it was, what becomes of the file that was there
 *    before.
 *
 * **The fourth is why this is a class rather than four lines in a handler.**
 * A file is deleted *after* the commit, never during: a write can still be
 * rolled back by a constraint, by an operation raising, or by a later row of
 * a bulk action — and a file deleted for a row that then stayed exactly as it
 * was is gone for good. An orphan in a bucket is the better failure, and the
 * only one this trade can produce.
 *
 * **Storing happens before the write, and that is the other half of the
 * trade.** The bytes have to exist before a row can point at them, so a write
 * that fails leaves a stored file nobody references. `discard()` is what the
 * handler calls to clean that up, on the paths where it knows the write did
 * not happen.
 */
final class FileWrites
{
    public function __construct(private readonly Storage $storage)
    {
    }

    /**
     * The columns a submission's files contribute, with the old keys to
     * forget afterwards.
     *
     * The stored row is read rather than trusted from the form: what a
     * replacement replaces is whatever the database says is there, not what a
     * hidden input claimed. That is also what makes the remove box safe — it
     * says "clear this column", and which file that is was never the
     * browser's to decide.
     *
     * @param  array<array-key, mixed> $body  the posted body, for the remove boxes
     * @param  array<string, mixed>  $stored  the row as it is now, empty for a create
     * @throws StorageException      when a file could not be stored, which fails the write
     */
    public function apply(
        PageDefinition $page,
        FormDefinition $form,
        Submission $submission,
        array $body,
        array $stored = [],
    ): FileWriteResult {
        /** @var array<array-key, mixed> $body */
        $columns = [];
        $written = [];
        $superseded = [];

        foreach ($form->fields as $key => $field) {
            if (!$field->type->isFile()) {
                continue;
            }

            $current = $this->currentKey($page, $field, $stored);
            $upload = $submission->file($key);

            if ($upload !== null && $upload->succeeded()) {
                $newKey = $this->store($page, $upload);

                $columns[$key] = $newKey;
                $written[] = $newKey;

                if ($current !== null && $current !== $newKey) {
                    $superseded[] = $current;
                }

                continue;
            }

            if ($this->removed($body, $key)) {
                // An explicit "take this off", which is the only thing that
                // clears the column: an empty file input never does.
                $columns[$key] = null;

                if ($current !== null) {
                    $superseded[] = $current;
                }
            }
        }

        return new FileWriteResult($columns, $written, $superseded);
    }

    /**
     * Whether this field still needs a file after everything above.
     *
     * `required` on a file cannot be checked where the other fields are
     * checked, because an edit that uploads nothing is an edit that keeps
     * what is stored -- and only here is it known whether anything is. The
     * message is the same one every other required field gives.
     *
     * @param  array<string, mixed> $columns what `apply()` decided
     * @param  array<string, mixed> $stored  the row as it is now
     * @return list<ValidationError>
     */
    public function missing(FormDefinition $form, array $columns, array $stored = []): array
    {
        $errors = [];

        foreach ($form->fields as $key => $field) {
            if (!$field->type->isFile() || !$field->required) {
                continue;
            }

            $after = \array_key_exists($key, $columns)
                ? $columns[$key]
                : ($stored[$key] ?? null);

            if (!\is_string($after) || $after === '') {
                $errors[] = new ValidationError($key, "{$field->label} is required.");
            }
        }

        return $errors;
    }

    /**
     * Forgets files a write did not end up using.
     *
     * Called on the paths where the handler knows the row was not written, so
     * that a refused save does not leave the bytes of a photograph nobody can
     * reach. A failure to delete is swallowed here and nowhere else: the
     * person is already being told their save failed, and "and also the
     * cleanup failed" is a sentence that helps nobody.
     *
     * @param list<string> $keys
     */
    public function discard(array $keys): void
    {
        foreach ($keys as $key) {
            try {
                $this->storage->delete($key);
            } catch (StorageException) {
                // An orphan, which is what this method exists to avoid and
                // not what it exists to report.
            }
        }
    }

    /**
     * Whether this is a key this admin would have written, and one this page
     * wrote.
     *
     * Asked of a column's value before anything is deleted -- by the handler
     * collecting what a deleted row owned, and by this class deciding what an
     * upload supersedes.
     *
     * **Both halves, and the page half is the one a review had to point out.**
     * `FormRegion` already refused to *show* a file whose key was not under
     * this page's folder; deleting one was left on the shape of the key
     * alone, which is the weaker check on the more destructive act. A column
     * can hold a key belonging to somewhere else -- an injection, a
     * hand-edited database, an import from an older version -- and the answer
     * to "this is not ours" must be the same whether the question is about
     * drawing it or about destroying it. It is not ours to delete.
     *
     * **Renaming a page strands its files**, and that is the price of tying
     * ownership to the page's name: keys written under the old name stop
     * being shown and stop being deleted, and become orphans the moment the
     * rename lands. It is the safe direction -- nothing is destroyed by a
     * rename -- and it is the kind of thing to know before renaming rather
     * than afterwards, so it is written here and in the guide.
     */
    public function ownedBy(mixed $value, PageDefinition $page): bool
    {
        return \is_string($value)
            && $value !== ''
            && $this->storage->accepts($value)
            && FileTypes::isUnder($value, $page->name);
    }

    /** @throws StorageException when the bytes did not land, which must fail the write */
    private function store(PageDefinition $page, UploadedFile $upload): string
    {
        $contents = $upload->contents();
        // Validated already -- `FieldValidator` refused anything this cannot
        // name -- so a null here is a file that changed under us between the
        // two reads, which is a thing to stop for rather than to guess at.
        $type = FileTypes::of($contents, $this->typesOf($page));

        if ($type === null) {
            throw new StorageException(
                'That file is not one this admin stores. It was accepted a moment ago and is not '
                . 'now, which should not happen: try uploading it again.',
            );
        }

        $key = FileTypes::keyFor($page->name, $type);

        $this->storage->put($key, $contents, $type);

        return $key;
    }

    /**
     * Every type any field of this page may hold.
     *
     * Read from the fields rather than from the installation's own list,
     * because by the time something is being stored the narrowing has already
     * happened in the validator -- this is the second check against the same
     * bytes, and it is here because `put()` is the last moment before a file
     * exists.
     *
     * @return list<string>
     */
    private function typesOf(PageDefinition $page): array
    {
        $types = [];

        foreach ($page->regions as $region) {
            foreach ($region->form->fields ?? [] as $field) {
                if ($field->type->isFile()) {
                    $types = [...$types, ...$field->accept];
                }
            }
        }

        // A page whose fields name no types at all accepts whatever the
        // installation does, which is what `UploadLimits` already decided.
        return $types === [] ? (new UploadLimits())->types : array_values(array_unique($types));
    }

    /** @param array<string, mixed> $stored */
    private function currentKey(PageDefinition $page, FieldDefinition $field, array $stored): ?string
    {
        $value = $stored[$field->key] ?? null;

        return $this->ownedBy($value, $page) && \is_string($value) ? $value : null;
    }

    /** @param array<array-key, mixed> $body */
    private function removed(array $body, string $field): bool
    {
        return \array_key_exists(FormFields::removeFor($field), $body);
    }
}
