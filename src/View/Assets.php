<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Http\UrlGenerator;

/**
 * A list of stylesheets and scripts a page loads, with content-hashed URLs.
 *
 * Bootstrap and the RockAdmin theme are always included. A project can layer
 * its own stylesheets and scripts on top, which will override in cascade
 * because they come last.
 */
final class Assets
{
    /** @var array<string, string> Cached content hashes, keyed by path */
    private array $hashes = [];

    /**
     * @param list<string> $projectStyles  URLs from configuration, loaded after the SDK's
     * @param list<string> $projectScripts
     * @param string|null  $root           the assets/ directory; null means this package's own
     */
    public function __construct(
        private readonly UrlGenerator $urls,
        private readonly array $projectStyles = [],
        private readonly array $projectScripts = [],
        private readonly ?string $root = null,
    ) {
    }

    /** @return list<string> */
    public function styles(): array
    {
        return [
            $this->url('vendor/bootstrap/bootstrap.min.css'),
            $this->url('css/rockadmin.css'),
            ...$this->projectStyles,
        ];
    }

    /** @return list<string> */
    public function scripts(): array
    {
        return [
            $this->url('vendor/bootstrap/bootstrap.bundle.min.js'),
            $this->url('js/core.js'),
            ...$this->projectScripts,
        ];
    }

    public function url(string $path): string
    {
        $normalized = $this->normalize($path);
        $file = $this->root($normalized);

        if (!is_file($file)) {
            throw new ViewException("Asset '{$normalized}' does not exist.");
        }

        if (!isset($this->hashes[$normalized])) {
            $hashValue = hash_file('xxh128', $file);
            if ($hashValue === false) {
                throw new ViewException("Asset '{$normalized}' could not be hashed.");
            }

            $this->hashes[$normalized] = substr($hashValue, 0, 12);
        }

        return $this->urls->route('assets', ['path' => $normalized], ['v' => $this->hashes[$normalized]]);
    }

    private function normalize(string $path): string
    {
        // An asset path keeps its extension, unlike a template name: the
        // extension is what decides the content type on the way out.
        $clean = $path;

        if ($clean === '') {
            throw new ViewException('An empty asset path cannot resolve to a file.');
        }

        // Each refusal says which rule was broken. This error reaches a person
        // who wrote a path in configuration, and "refused" without "why" sends
        // them reading the source to find out.
        if (str_contains($clean, "\0")) {
            throw new ViewException("Refusing '{$path}' as an asset path: it contains a null byte.");
        }

        if (str_contains($clean, '\\')) {
            throw new ViewException(
                "Refusing '{$path}' as an asset path: separate segments with '/', on every platform.",
            );
        }

        if (str_starts_with($clean, '/')) {
            throw new ViewException(
                "Refusing '{$path}' as an asset path: it must be relative to the asset directory.",
            );
        }

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '') {
                throw new ViewException("Refusing '{$path}' as an asset path: it has an empty segment.");
            }

            if ($segment === '.' || $segment === '..') {
                throw new ViewException(
                    "Refusing '{$path}' as an asset path: a path is relative to the asset directory and cannot leave it.",
                );
            }

            if (str_contains($segment, ':')) {
                throw new ViewException(
                    "Refusing '{$path}' as an asset path: it names no drive or stream wrapper.",
                );
            }
        }

        return $clean;
    }

    private function root(string $normalized): string
    {
        $base = $this->root ?? \dirname(__DIR__, 2) . '/assets';

        return $base . '/' . $normalized;
    }
}
