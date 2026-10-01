<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** A loaded, validated configuration. */
final class Config
{
    /**
     * Bumped whenever the shape this class exports changes.
     *
     * `Cache` writes a compiled configuration with `var_export()` and reads it
     * back through `__set_state()`, so a cache file written by an older
     * RockAdmin restores an object missing whatever was added since. That is
     * not a loud failure: it is a configuration that looks valid and is
     * quietly incomplete -- a cache from before roles existed would restore
     * with no roles at all, and once the gate is in place no-roles means
     * nobody may do anything, with nothing anywhere saying why.
     *
     * So the version travels with the file, and a mismatch makes `Cache` treat
     * it as absent and rebuild. Raise this when you add a constructor
     * argument, never otherwise.
     */
    public const int CACHE_VERSION = 3;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $roles the contents of roles.php, expanded and resolved but not
     *                                    yet interpreted — `Roles::fromConfig()` needs the catalogue
     *                                    of permissions the pages generated, and the loader has no
     *                                    page repository. Rule 4: the loader does not reach into
     *                                    the pages to build something it does not itself use.
     */
    public function __construct(
        private readonly array $data,
        private readonly Enums $enums,
        private readonly array $roles = [],
        /**
         * The contents of workspaces.php, in the same half-built state and
         * for the same reason: which pages exist is the pages' business, and
         * `Workspaces::fromConfig()` is given the catalogue by whoever has
         * one. An empty array is the single-tenant case — no workspaces,
         * nothing scoped, nothing to pay for.
         *
         * @var array<string, mixed>
         */
        private readonly array $workspaces = [],
        /**
         * The version of the class that produced this object. Only ever
         * differs from CACHE_VERSION for an object restored from a stale
         * cache file, which `Cache::read()` discards.
         */
        public readonly int $cacheVersion = self::CACHE_VERSION,
    ) {
    }

    /** Whether this object was built by the current shape of the class. */
    public function isCurrent(): bool
    {
        return $this->cacheVersion === self::CACHE_VERSION;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $path): bool
    {
        $value = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function enums(): Enums
    {
        return $this->enums;
    }

    /** @return array<string, mixed> */
    public function roles(): array
    {
        return $this->roles;
    }

    /** @return array<string, mixed> */
    public function workspaces(): array
    {
        return $this->workspaces;
    }

    /**
     * @param array{data: array<string, mixed>, enums: Enums, roles?: array<string, mixed>,
     *              workspaces?: array<string, mixed>, cacheVersion?: int} $state written by var_export()
     *
     * The defaults are not compatibility shims: they exist so that a stale
     * cache file can be *constructed* without a TypeError, and then
     * recognised as stale by `isCurrent()` and discarded. Without them the
     * upgrade fails here, before anything can tell the difference between a
     * corrupt file and an old one.
     */
    public static function __set_state(array $state): self
    {
        return new self(
            $state['data'],
            $state['enums'],
            $state['roles'] ?? [],
            $state['workspaces'] ?? [],
            $state['cacheVersion'] ?? 1,
        );
    }
}
