<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Http\Handler;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;

/**
 * Serves assets from the assets directory.
 *
 * This is the only thing between a visitor and the file system. It validates
 * paths to prevent traversal, checks extensions against an allowed list, and
 * sets cache headers because URLs carry their content hash.
 */
final class AssetHandler implements Handler
{
    private const TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ico' => 'image/x-icon',
        'map' => 'application/json',
    ];

    public function __construct(private readonly ?string $root = null)
    {
    }

    public function handle(Route $route, Request $request): Response
    {
        $path = $route->param('path');
        $normalized = $this->normalize($path);
        $file = $this->resolve($normalized);

        if (!is_file($file)) {
            throw new NotFoundException("Asset '{$normalized}' not found.");
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new NotFoundException("Asset '{$normalized}' could not be read.");
        }

        $extension = $this->getExtension($normalized);
        if (!isset(self::TYPES[$extension])) {
            throw new NotFoundException("Asset '{$normalized}' has an unsupported file type.");
        }

        $contentType = self::TYPES[$extension];

        return new Response(200, $contents, [
            'content-type' => $contentType,
            'cache-control' => 'public, max-age=31536000, immutable',
            'x-content-type-options' => 'nosniff',
        ]);
    }

    private function normalize(string $path): string
    {
        $clean = $path;

        if ($clean === '') {
            throw new NotFoundException('An empty asset path cannot be served.');
        }

        // Each refusal says which rule was broken. This error reaches a person
        // who made a request, and "not found" without "why" sends them looking
        // at the URL they constructed.
        if (str_contains($clean, "\0")) {
            throw new NotFoundException("Refusing to serve '{$path}': it contains a null byte.");
        }

        if (str_contains($clean, '\\')) {
            throw new NotFoundException(
                "Refusing to serve '{$path}': separate segments with '/', on every platform.",
            );
        }

        if (str_starts_with($clean, '/')) {
            throw new NotFoundException(
                "Refusing to serve '{$path}': it must be relative to the asset directory.",
            );
        }

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '') {
                throw new NotFoundException("Refusing to serve '{$path}': it has an empty segment.");
            }

            if ($segment === '.' || $segment === '..') {
                throw new NotFoundException(
                    "Refusing to serve '{$path}': a path is relative to the asset directory and cannot leave it.",
                );
            }

            if (str_contains($segment, ':')) {
                throw new NotFoundException(
                    "Refusing to serve '{$path}': it names no drive or stream wrapper.",
                );
            }
        }

        return $clean;
    }

    private function resolve(string $normalized): string
    {
        $base = $this->root ?? \dirname(__DIR__, 2) . '/assets';
        $basePath = realpath($base);

        if ($basePath === false) {
            throw new NotFoundException('Asset directory not found.');
        }

        $filePath = realpath($base . '/' . $normalized);

        if ($filePath === false) {
            throw new NotFoundException("Asset '{$normalized}' does not exist or cannot be resolved.");
        }

        // Check that the resolved path is inside the asset directory.
        if (!str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR) && $filePath !== $basePath) {
            throw new NotFoundException("Asset '{$normalized}' is outside the asset directory.");
        }

        return $filePath;
    }

    private function getExtension(string $path): string
    {
        $parts = explode('.', $path);

        return end($parts) ?: '';
    }
}
