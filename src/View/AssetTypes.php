<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * The file extensions this admin ever serves as an asset, and the content
 * type each is sent with.
 *
 * `Assets` mints URLs and `AssetHandler` serves them; both have to agree on
 * what is servable, or one mints a URL the other 404s on. Keeping the list in
 * one place is what makes that agreement automatic instead of a second file
 * to remember to update.
 */
final class AssetTypes
{
    /** @var array<string, string> lower-case extension => content type */
    private const TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'mjs' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'ico' => 'image/x-icon',
        'map' => 'application/json',
    ];

    public static function isKnown(string $extension): bool
    {
        return isset(self::TYPES[strtolower($extension)]);
    }

    public static function contentType(string $extension): ?string
    {
        return self::TYPES[strtolower($extension)] ?? null;
    }

    /**
     * The extension a path is served or minted under: whatever follows the
     * last dot in its final segment. Splitting the whole path rather than the
     * basename would read a dot in a directory name as the extension.
     */
    public static function extensionOf(string $path): string
    {
        $basename = basename(str_replace('\\', '/', $path));
        $dot = strrpos($basename, '.');

        return $dot === false ? '' : substr($basename, $dot + 1);
    }
}
