<?php

declare(strict_types=1);

namespace RockAdmin\Form;

/**
 * The file a form already has, prepared for its control.
 *
 * A template may not ask a `Storage` anything — rule 4, and the same reason a
 * template never sees a `Connection`: building a URL is a decision about
 * signing and expiry, and a view object is where decisions arrive already
 * made. So the region resolves the key into an address once and hands this
 * over.
 *
 * Null in place of one of these means the row has no file, which is a
 * different thing from a file whose URL could not be built — the second is an
 * installation that is wired wrong and says so, rather than a control that
 * quietly shows nothing.
 */
final class StoredFileView
{
    public function __construct(
        /** The column's value: what identifies the file, and what a replacement overwrites. */
        public readonly string $key,
        /** Where it can be fetched, signed or public depending on the storage. */
        public readonly string $url,
        /** Whether to draw it or to link to it. */
        public readonly bool $isImage,
        /** The last segment of the key, which is all a person needs to tell two apart. */
        public readonly string $name,
    ) {
    }

    public static function of(string $key, string $url): self
    {
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));

        return new self(
            $key,
            $url,
            \in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
            basename($key),
        );
    }
}
