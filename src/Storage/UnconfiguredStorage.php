<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

/**
 * The storage an installation has when it has not set one up.
 *
 * It refuses everything, with the message the configuration would have
 * refused with — so the explanation is the same, and only the moment moves:
 * from booting the admin to the first time somebody tries to upload.
 *
 * **That move is the point.** `storage.path` being empty used to stop the
 * whole admin from starting, which meant an installation that administers
 * rows and uploads nothing could not run at all until it had chosen a
 * directory for files it will never have. A feature nobody uses must not be
 * able to take the admin down; a feature somebody uses must say why it does
 * not work, where they are using it. `doctor` reports it too, so it is not
 * only discoverable by tripping over it.
 *
 * `accepts()` answers false, which is what keeps the refusal quiet where it
 * should be: a grid with an `image` column over an installation like this
 * draws the file's name rather than a broken picture, by the same path it
 * uses when a key belongs to another page.
 */
final class UnconfiguredStorage implements Storage
{
    public function __construct(private readonly string $reason)
    {
    }

    public function put(string $key, string $contents, string $contentType): void
    {
        throw new StorageException($this->reason);
    }

    public function get(string $key): string
    {
        throw new StorageException($this->reason);
    }

    public function delete(string $key): void
    {
        // Nothing was ever written, so there is nothing to forget. Raising
        // here would make deleting a row fail on an installation that has no
        // files at all.
    }

    public function exists(string $key): bool
    {
        return false;
    }

    public function url(string $key): string
    {
        throw new StorageException($this->reason);
    }

    public function accepts(string $key): bool
    {
        return false;
    }
}
