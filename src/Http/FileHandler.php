<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Storage\FileLink;
use RockAdmin\Storage\FileTypes;
use RockAdmin\Storage\Storage;
use RockAdmin\Storage\StorageException;
use RockAdmin\Storage\StorageKey;

/**
 * `GET /_file/{key...}` — an uploaded file, handed back to the browser.
 *
 * Three checks in this order, and the order is the point: the key has to be
 * one this admin could have written, the signature has to be one this session
 * was given, and only then is the storage asked for anything. A path with
 * `..` in it is refused before any driver sees it, so `LocalStorage` is never
 * the thing standing between a URL and the file system — it checks too, and
 * two checks is the arrangement, not a duplication to tidy away.
 *
 * **The signature is what makes this access control**, and `FileLink` is
 * where the reasoning lives: the route being authenticated only says that a
 * stranger is out, and in a multi-tenant admin that is not the question being
 * asked. Per-page and per-row permissions on files are v1.1 -- specification
 * 13 puts column-level permissions out of v1 -- and the guide says so plainly
 * rather than leaving it to be discovered.
 *
 * **Nothing here trusts a stored content type, because nothing stores one.**
 * The type comes from the key's own extension, which `FileTypes::keyFor()`
 * wrote from the bytes at upload time after identifying them; a key with any
 * other extension is served as a download of unknown type. `nosniff` goes on
 * every response, so a browser never improves on that answer.
 */
final class FileHandler implements Handler
{
    public function __construct(
        private readonly Storage $files,
        private readonly FileLink $links,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $key = $route->param('key');

        // Not `StorageKey::require()`: that raises a StorageException, which
        // is a 500, and a URL somebody typed badly is a 404. The key is also
        // not repeated back -- it is attacker-controlled text, and an error
        // page is the wrong place to echo it.
        if (!StorageKey::isValid($key) || !$this->files->accepts($key)) {
            throw new NotFoundException('That is not a file this admin wrote.');
        }

        if (!$this->links->isValid(
            $key,
            $request->query(FileLink::EXPIRY),
            $request->query(FileLink::SIGNATURE),
        )) {
            // One answer for "unsigned", "signed for somebody else" and
            // "signed too long ago". They are the same fact from here --
            // this request may not have this file -- and telling them apart
            // would say whether a key exists to somebody holding one.
            throw new ForbiddenException(
                'This link has expired or was not made for you. Open the page the file belongs to '
                . 'and follow the link from there.',
            );
        }

        try {
            $contents = $this->files->get($key);
        } catch (StorageException) {
            throw new NotFoundException('That file is no longer there.');
        }

        $type = FileTypes::typeOfExtension(pathinfo($key, PATHINFO_EXTENSION));

        return new Response(200, $contents, [
            'content-type' => $type ?? 'application/octet-stream',
            'content-disposition' => $this->disposition($key, $type),
            'content-length' => (string) \strlen($contents),
            'x-content-type-options' => 'nosniff',
            // Private, because the URL is signed for one session and a shared
            // cache holding the answer would outlive both the signature and
            // the reason for it. `no-store` rather than a short max-age: an
            // admin is not a place to leave somebody's uploads on disk.
            'cache-control' => 'private, no-store',
        ]);
    }

    /**
     * Shown in the page, or saved.
     *
     * An image this admin identified is drawn; everything else is a download,
     * including a PDF -- a PDF viewer is a large piece of software reading an
     * untrusted file in the origin the admin runs in, and `attachment` is the
     * cheaper answer. The filename is the key's own last segment, which is
     * hexadecimal and an extension, so there is nothing in it to quote badly.
     */
    private function disposition(string $key, ?string $type): string
    {
        $name = basename($key);

        if ($type !== null && FileTypes::isImage($type)) {
            return 'inline; filename="' . $name . '"';
        }

        return 'attachment; filename="' . $name . '"';
    }
}
