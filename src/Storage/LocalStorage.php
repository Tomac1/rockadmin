<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

use RockAdmin\Http\UrlGenerator;
use Throwable;

/**
 * Files in a directory, served through the admin.
 *
 * The driver that needs nothing configured but a path, and the one a project
 * starts with: a laptop, a single server, a shared host with no object store
 * anywhere near it.
 *
 * **The directory belongs outside the document root**, the same rule
 * `paths.logs` already follows and for a sharper reason: a directory of
 * uploads inside it is a directory of files a stranger can fetch by guessing,
 * and — on a server configured to run PHP by extension rather than by
 * location — one they may be able to execute. Nothing here can enforce where
 * the path points, so the schema says it, the documentation says it, and the
 * default route serves files through PHP where the gate can see them.
 *
 * **A public base URL is the other mode**, for the installation that puts its
 * uploads behind a CDN on purpose. It is opt-in, and configuration is where
 * that decision belongs: the default must not put somebody's scanned invoices
 * on the open internet because nobody chose.
 */
final class LocalStorage implements Storage
{
    public function __construct(
        /** The directory files live under, absolute, outside the document root. */
        private readonly string $root,
        /**
         * How a URL is built.
         *
         * With a `UrlGenerator` the file is served by the admin's own route,
         * where `Gate` has already decided whether the person may be here at
         * all. Without one there is no private URL to build, and `url()` says
         * so rather than guessing a path that may or may not be reachable.
         */
        private readonly ?UrlGenerator $urls = null,
        /**
         * A base URL for an installation that serves the directory itself.
         *
         * Empty is the private default. Set, it wins: a file reachable over
         * HTTP without PHP in the way is a file the gate never sees, which is
         * the trade an installation makes knowingly when it configures this.
         */
        private readonly string $publicBaseUrl = '',
    ) {
    }

    public function put(string $key, string $contents, string $contentType): void
    {
        $file = $this->path($key);
        $directory = \dirname($file);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new StorageException(\sprintf(
                "The upload directory '%s' does not exist and could not be created. The storage path "
                . 'in rockadmin.php has to be writable, and outside the document root.',
                $directory,
            ));
        }

        try {
            $written = file_put_contents($file, $contents, LOCK_EX);
        } catch (Throwable $e) {
            throw new StorageException("'{$key}' could not be written: " . $e->getMessage(), previous: $e);
        }

        if ($written === false) {
            throw new StorageException("'{$key}' could not be written.");
        }
    }

    public function get(string $key): string
    {
        $contents = @file_get_contents($this->path($key));

        if ($contents === false) {
            throw new StorageException("There is no file stored as '{$key}'.");
        }

        return $contents;
    }

    public function delete(string $key): void
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            // Already gone, which is the normal case rather than a failure:
            // see the interface.
            return;
        }

        if (!@unlink($file)) {
            throw new StorageException("'{$key}' is still there and could not be removed.");
        }
    }

    public function exists(string $key): bool
    {
        return StorageKey::isValid($key) && is_file($this->path($key));
    }

    public function url(string $key): string
    {
        StorageKey::require($key);

        if ($this->publicBaseUrl !== '') {
            return rtrim($this->publicBaseUrl, '/') . '/' . $key;
        }

        if ($this->urls === null) {
            throw new StorageException(\sprintf(
                "There is no way to build a URL for '%s': this storage was wired without a "
                . 'UrlGenerator and without a public base URL, so the file can be written and never '
                . 'shown. Pass one of the two.',
                $key,
            ));
        }

        return $this->urls->route('file', ['key' => $key]);
    }

    public function accepts(string $key): bool
    {
        return StorageKey::isValid($key);
    }

    /**
     * The key as a path under the root.
     *
     * `StorageKey::require()` has already refused anything with `..` in it, a
     * leading slash or a backslash, so this is concatenation rather than
     * resolution -- and deliberately so: `realpath()` here would follow a
     * symlink out of the directory and then report that everything was fine.
     */
    private function path(string $key): string
    {
        return rtrim(str_replace('\\', '/', $this->root), '/') . '/' . StorageKey::require($key);
    }
}
