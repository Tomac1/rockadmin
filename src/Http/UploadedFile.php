<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * One file as the browser sent it, before anybody has believed anything about
 * it.
 *
 * `$_FILES` is a second superglobal with a shape of its own, and this is the
 * one place that reads it. Everything downstream -- validation, the write
 * path, the storage -- takes this object instead, which is why a test can
 * build one from a temporary file and never touch a superglobal.
 *
 * **Two of its four properties are claims.** `$originalName` and the content
 * type the browser sent are whatever the person's computer said; the name is
 * kept for showing and never for building a path, and the browser's type is
 * not kept at all -- `FileTypes` reads the bytes instead. `$bytes` and
 * `$error` come from PHP.
 *
 * **An absent file is not an error.** A form with a file field that nobody
 * filled in posts `UPLOAD_ERR_NO_FILE`, which means "leave this alone" on an
 * edit and "there is nothing here" on a create. Telling that apart from a
 * failure is this class's main job.
 */
final class UploadedFile
{
    public function __construct(
        /** What the person called it. Shown, never used to build a path. */
        public readonly string $originalName,
        /** Where PHP put it, which is a path this process may read exactly once. */
        public readonly string $temporaryPath,
        public readonly int $bytes,
        /** One of PHP's `UPLOAD_ERR_*`. */
        public readonly int $error = UPLOAD_ERR_OK,
    ) {
    }

    /**
     * The files of a request, by field name.
     *
     * Only flat fields: `name="photo"`, never `name="photos[]"`. A multiple
     * upload has a different `$_FILES` shape -- parallel arrays rather than
     * one entry -- and nothing in this admin declares one, so reading it here
     * would be code with no caller and one more shape to get wrong.
     *
     * @param  array<array-key, mixed>    $files normally `$_FILES`, whose keys PHP types as
     *                                           array-key like every other superglobal
     * @return array<string, UploadedFile>
     */
    public static function mapOf(array $files): array
    {
        $uploads = [];

        foreach ($files as $field => $file) {
            if (!\is_array($file) || \is_array($file['name'] ?? null)) {
                continue;
            }

            $uploads[(string) $field] = new self(
                self::text($file['name'] ?? null),
                self::text($file['tmp_name'] ?? null),
                \is_int($file['size'] ?? null) ? $file['size'] : 0,
                \is_int($file['error'] ?? null) ? $file['error'] : UPLOAD_ERR_NO_FILE,
            );
        }

        return $uploads;
    }

    /** Whether the person actually chose a file. */
    public function isPresent(): bool
    {
        return $this->error !== UPLOAD_ERR_NO_FILE && $this->originalName !== '';
    }

    public function succeeded(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->temporaryPath !== '';
    }

    /**
     * Why it did not arrive, in a sentence for the person who chose it, or
     * null when it did.
     *
     * PHP's codes are translated rather than passed through, because
     * "UPLOAD_ERR_CANT_WRITE" tells an administrator nothing they can act on
     * and tells an attacker which part of the server is broken. The two
     * size errors say the same thing on purpose: whether `upload_max_filesize`
     * or a form limit stopped it is the installation's business, and "it was
     * too big" is all the person can do anything about.
     */
    public function failure(): ?string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too large to upload.',
            UPLOAD_ERR_PARTIAL => 'That file only arrived in part. Try again.',
            default => 'That file could not be received. Tell whoever administers this installation.',
        };
    }

    /**
     * The bytes.
     *
     * Read whole, because an admin upload is capped at a few megabytes and
     * every driver's `put()` takes a string -- streaming would be the right
     * answer for a video and the wrong complexity for a photograph.
     */
    public function contents(): string
    {
        $contents = $this->succeeded() ? @file_get_contents($this->temporaryPath) : false;

        return $contents === false ? '' : $contents;
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
