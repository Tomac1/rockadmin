<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

/**
 * What a storage knows about one file it holds.
 *
 * The key is the whole of its identity: it is what goes in the column, what
 * builds the URL and what deletes it later. Everything else here is the
 * storage's answer about the bytes, not a claim the browser made.
 *
 * There is no URL property, and that is deliberate. A URL is built when
 * something is about to be rendered, by the storage that holds the file --
 * `LocalStorage` builds a route, `S3Storage` signs one that expires in
 * minutes. Putting one here would mean deciding, at the moment of the upload,
 * how the file will be reached for the rest of its life.
 */
final class StoredFile
{
    public function __construct(
        /** Where it lives, and what the column holds: `ads/2026/10/8f3a….jpg`. */
        public readonly string $key,
        /** The type derived from the bytes, never the one the browser claimed. */
        public readonly string $contentType,
        public readonly int $bytes,
        /**
         * What the person called it when they uploaded it.
         *
         * Kept for showing -- "faktura-2026.pdf" is more use on a page than
         * `8f3a….pdf` -- and never used to build a path. It is the one part of
         * this object that somebody else chose.
         */
        public readonly string $originalName = '',
    ) {
    }

    /** Whether this is something a browser will draw rather than download. */
    public function isImage(): bool
    {
        return str_starts_with($this->contentType, 'image/');
    }
}
