<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

/**
 * What a file actually is, and what it will be called.
 *
 * **The browser's `Content-Type` is not consulted at all.** It is a header the
 * person uploading chose, so an admin that believed it would store a PHP
 * script as `image/jpeg` because the form said so. The type comes from the
 * first bytes of the file, which is the one thing about an upload that is not
 * somebody's claim.
 *
 * **The extension comes from the detected type**, never from the uploaded
 * name. `faktura.php.jpg` is stored as `….jpg` if it is a JPEG and refused if
 * it is not; a server later pointed at the directory by mistake finds nothing
 * it will execute, and the stored name carries no second extension to argue
 * about.
 *
 * **The table is short on purpose.** Five types an admin has a reason to
 * accept, each with a signature that is a constant at a known offset. Adding
 * one is a line here plus a line in the default allowlist; accepting
 * "whatever the project configures" without a signature for it is how an
 * allowlist becomes a formality.
 *
 * `finfo` is asked as a second opinion when the extension is loaded, and
 * disagreement is refusal: it is not required (rule 1 keeps `composer.json` at
 * four extensions), and where it exists it catches the kinds of file this
 * table was never taught about.
 */
final class FileTypes
{
    /**
     * Content type => [signature, offset, extension].
     *
     * WebP is the only one whose signature is in two pieces: `RIFF` at 0 and
     * `WEBP` at 8, with the file size in between.
     *
     * @var array<string, array{0: list<array{0: string, 1: int}>, 1: string}>
     */
    private const array TYPES = [
        'image/jpeg' => [[["\xFF\xD8\xFF", 0]], 'jpg'],
        'image/png' => [[["\x89PNG\r\n\x1A\n", 0]], 'png'],
        'image/gif' => [[['GIF8', 0]], 'gif'],
        'image/webp' => [[['RIFF', 0], ['WEBP', 8]], 'webp'],
        'application/pdf' => [[['%PDF-', 0]], 'pdf'],
    ];

    /**
     * The type these bytes are, or null when they are none this admin knows.
     *
     * Null is the answer for an empty file too: nothing is not a type, and a
     * zero-byte upload is almost always a form that went wrong rather than a
     * file somebody meant to send.
     *
     * @param list<string> $allowed the installation's allowlist; a type this knows but the
     *                              installation does not want answers null as well
     */
    public static function of(string $contents, array $allowed): ?string
    {
        if ($contents === '') {
            return null;
        }

        foreach (self::TYPES as $type => [$signatures, $extension]) {
            if (!\in_array($type, $allowed, true)) {
                continue;
            }

            foreach ($signatures as [$signature, $offset]) {
                if (substr($contents, $offset, \strlen($signature)) !== $signature) {
                    continue 2;
                }
            }

            return self::confirmed($contents, $type) ? $type : null;
        }

        return null;
    }

    /** The extension a stored file gets, without a dot. */
    public static function extensionOf(string $type): string
    {
        return self::TYPES[$type][1] ?? 'bin';
    }

    /**
     * The type a stored key's extension says it is, for serving it back.
     *
     * The other direction of the same table, and it is a *lookup* rather than
     * a guess: the extension was written by `keyFor()` from bytes this class
     * had already identified, so a key ending in `.png` is a file whose first
     * eight bytes were a PNG signature when it was stored. Nothing a browser
     * or a person said is involved.
     *
     * Null for anything else, which a caller serves as a download rather than
     * inventing a type for.
     */
    public static function typeOfExtension(string $extension): ?string
    {
        foreach (self::TYPES as $type => [, $known]) {
            if ($known === strtolower($extension)) {
                return $type;
            }
        }

        return null;
    }

    /** Whether a browser should draw this in the page rather than save it. */
    public static function isImage(string $type): bool
    {
        return str_starts_with($type, 'image/');
    }

    /**
     * A key for a file of this type, under this prefix.
     *
     * Dated, so a bucket that has been running for three years can still be
     * looked through by hand, and random, so nothing about it can be guessed
     * from the row it belongs to -- not the id, not the name of the thing it
     * is a photo of, not the order it was uploaded in. Sixteen bytes of
     * randomness is the part that makes a key worth nothing to somebody who
     * does not already have it.
     *
     * @param string $prefix a page's own folder, already lower-case and slash-free
     */
    public static function keyFor(string $prefix, string $type): string
    {
        return \sprintf(
            '%s/%s/%s.%s',
            self::folderFor($prefix),
            gmdate('Y/m'),
            bin2hex(random_bytes(16)),
            self::extensionOf($type),
        );
    }

    /**
     * The folder a prefix becomes, which is also what a reader checks a key
     * against.
     *
     * One place, because the two sides have to agree: a key is written under
     * the folder a page's name slugs to, and a form will only build a URL for
     * a key that is under its own. Two spellings of this would mean a page
     * that could sign links to files it did not write.
     *
     * Trimmed before the fallback, not after: a prefix of nothing but
     * separators cleans up to nothing, and a key beginning with a slash is
     * one `StorageKey` refuses -- an upload that fails at the last step
     * because the page is called `///` is a worse answer than a folder called
     * `files`.
     */
    public static function folderFor(string $prefix): string
    {
        $folder = trim(strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $prefix) ?? ''), '-');

        return $folder === '' ? 'files' : $folder;
    }

    /**
     * Whether this key belongs to that prefix.
     *
     * Asked before a URL is built for a value that came out of a column. The
     * column is data, and data outlives the code that wrote it: a row may
     * carry a key from another page because somebody edited the database,
     * because an older version of this admin wrote it, or because a bug
     * somewhere let a string through. None of those is a reason to hand out a
     * working link to it.
     */
    public static function isUnder(string $key, string $prefix): bool
    {
        return str_starts_with($key, self::folderFor($prefix) . '/');
    }

    /**
     * What `finfo` makes of the same bytes, where it exists.
     *
     * Agreement or silence, never a veto by absence: an installation without
     * `ext-fileinfo` gets the signature check alone, which is what this class
     * would be in any case. It is here because the signature table knows five
     * types and `finfo` knows a thousand -- so a file whose first three bytes
     * look like a JPEG and whose rest is something else is caught by the
     * second opinion and by nothing else.
     */
    private static function confirmed(string $contents, string $type): bool
    {
        if (!class_exists(\finfo::class)) {
            return true;
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (!\is_string($detected) || $detected === '') {
            return true;
        }

        // JPEG is the one with more than one spelling in the wild, and
        // `image/jpg` is what some builds answer.
        return $detected === $type
            || ($type === 'image/jpeg' && $detected === 'image/jpg');
    }
}
