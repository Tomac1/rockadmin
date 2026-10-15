<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

/**
 * Where uploaded files live.
 *
 * The seam, and the fourth of its kind in this project after `SessionStore`,
 * `Mailer` and `RowSource`: a host with its own disk abstraction -- Laravel's
 * `Storage`, Flysystem, an existing S3 wrapper with its own credentials and
 * its own retry policy -- wires one object and this SDK's own drivers never
 * run. Specification 3 names "an S3 uploader" as exactly this arrangement.
 *
 * **A key is not a path and not a URL.** It is an opaque string the storage
 * assigned, it is what the database column holds, and it is the only thing
 * that is ever written down. `url()` turns one into something a browser can
 * fetch at the moment of rendering, which is when the answer is actually known
 * -- a bucket can move, a CDN domain can change, and a signed URL expires.
 *
 * **Nothing here takes a filename from the person uploading.** `put()` is
 * given the key the caller decided on, and `UploadedFile` is where that
 * decision is made from the *bytes* rather than from what the browser said
 * they were called.
 *
 * **Every implementation is private by default.** A driver that cannot keep a
 * file from being fetched by a stranger is not one this interface describes;
 * `url()` either returns an address only a signed-in administrator can use, or
 * one the installation deliberately made public in its configuration.
 */
interface Storage
{
    /**
     * Writes the bytes under this key, replacing anything already there.
     *
     * @param  string           $contents the whole file; an admin upload is capped far below the
     *                                    size where streaming would be the only way
     * @throws StorageException when the bytes did not land
     */
    public function put(string $key, string $contents, string $contentType): void;

    /**
     * Reads them back.
     *
     * @throws StorageException when there is nothing under that key, which is
     *                          a different thing from a file this cannot reach
     */
    public function get(string $key): string;

    /**
     * Forgets the file, and says nothing if it was already gone.
     *
     * Deleting something that is not there is the normal case, not an error:
     * it happens whenever a row is deleted twice, whenever a replaced file was
     * swept up by something else, and whenever an earlier deletion half
     * succeeded. Raising for it would mean every caller writing the same
     * try/catch.
     *
     * @throws StorageException when the file is there and could not be removed
     */
    public function delete(string $key): void;

    public function exists(string $key): bool;

    /**
     * Where a browser can fetch it, for as long as the answer is good for.
     *
     * Not stored anywhere: a public URL outlives its bucket and a signed one
     * expires, so both are built when something is about to be rendered.
     */
    public function url(string $key): string;

    /** What this storage will accept as a key, so a caller can refuse early. */
    public function accepts(string $key): bool;
}
