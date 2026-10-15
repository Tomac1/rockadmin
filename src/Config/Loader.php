<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use Closure;

/**
 * Reads a configuration directory and runs the passes in order.
 *
 * The order is the design: references expand before placeholders resolve, so a
 * shared definition may contain one; validation runs last, so it judges what
 * the admin will actually run on rather than what was typed.
 */
final class Loader
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * Keys holding a path, resolved against the project root.
     *
     * Specification 11.4: configuration is committed once and must be
     * identical everywhere, so a path in it is relative to the project root
     * rather than absolute — and relative to the *project* root, not the
     * document root, because `storage/logs/rockadmin` under `public/` is a
     * reset link somebody can fetch. The list is here, next to where the
     * configuration is assembled, rather than spread over the classes that
     * read each key: one place to look, and one place a new path key has to
     * be added to.
     *
     * `template_paths` is a list of them and is handled with them.
     */
    private const PATH_KEYS = ['paths.logs', 'paths.cache', 'storage.path'];

    /**
     * @param Closure(string): ?string $env
     * @param ?string                  $basePath the project root; without it, relative paths are
     *                                           left as written and resolved by whoever opens them,
     *                                           which is the working directory
     */
    public function __construct(
        private readonly string $directory,
        private readonly Closure $env,
        private readonly ?string $basePath = null,
    ) {
    }

    public function load(): Config
    {
        if (!is_dir($this->directory)) {
            throw new ConfigException("Configuration directory not found: {$this->directory}");
        }

        $root = $this->merge($this->read('rockadmin.php'), $this->read('rockadmin.local.php'));

        $definitions = new Definitions($this->definitionsFrom($this->read('defs.php')));
        $expanded = $definitions->expand($root);

        $resolver = new Resolver($this->env, $root);
        $resolved = $resolver->resolve($expanded);

        $schema = RootSchema::create();
        $errors = (new Validator())->validate($resolved, $schema);

        if ($errors !== []) {
            throw new ConfigException($this->report($errors));
        }

        // Defaults are applied after validation, so the validator judges what the
        // project wrote rather than what we completed for it.
        $resolved = (new Defaults())->apply($resolved, $schema);
        $resolved = $this->resolvePaths($resolved);

        $enums = $definitions->expand($this->read('enums.php'));
        $resolvedEnums = $resolver->resolve($enums);

        // Roles load exactly the way enumerations do -- shared definitions
        // expanded, placeholders resolved -- but they are not interpreted
        // here. Which permissions exist is decided by the pages, and the
        // loader has no page repository; `Roles::fromConfig()` is given the
        // catalogue by whoever has one.
        $roles = $resolver->resolve($definitions->expand($this->read('roles.php')));

        // Workspaces load the same way and for the same reason: which pages
        // exist is decided by the pages, and the loader has no page
        // repository. `Workspaces::fromConfig()` is given the catalogue by
        // whoever has one. A missing file is an empty array, which is the
        // single-tenant case -- no workspaces, nothing scoped, nothing to pay
        // for.
        $workspaces = $resolver->resolve($definitions->expand($this->read('workspaces.php')));

        $this->warnings = $resolver->warnings();

        return new Config($resolved, Enums::fromConfig($resolvedEnums), $roles, $workspaces);
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Rewrites every path key that was written relative to the project root.
     *
     * An absolute path is left alone, because a host that wrote one meant it
     * — a bucket mount, a RAM disk, a directory shared between two
     * applications. Windows drive letters count as absolute, which is what
     * the second test is for.
     *
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function resolvePaths(array $config): array
    {
        if ($this->basePath === null) {
            return $config;
        }

        foreach (self::PATH_KEYS as $key) {
            $value = $this->at($config, $key);

            if (\is_string($value) && $value !== '') {
                $config = $this->put($config, $key, $this->absolute($value));
            }
        }

        $templates = $config['template_paths'] ?? null;

        if (\is_array($templates)) {
            $resolved = [];

            foreach ($templates as $path) {
                $resolved[] = \is_string($path) ? $this->absolute($path) : $path;
            }

            $config['template_paths'] = $resolved;
        }

        return $config;
    }

    private function absolute(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : rtrim((string) $this->basePath, '/\\') . '/' . $path;
    }

    /** @param array<string, mixed> $config */
    private function at(array $config, string $path): mixed
    {
        $value = $config;

        foreach (explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function put(array $config, string $path, string $value): array
    {
        $segments = explode('.', $path);
        $first = array_shift($segments);

        if ($segments === []) {
            $config[$first] = $value;

            return $config;
        }

        $child = $config[$first] ?? [];

        /** @var array<string, mixed> $child narrows array<mixed, mixed> */
        $child = \is_array($child) ? $child : [];
        $config[$first] = $this->put($child, implode('.', $segments), $value);

        return $config;
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        $path = $this->directory . '/' . $file;

        if (!is_file($path)) {
            return [];
        }

        $contents = require $path;

        if (!\is_array($contents)) {
            throw new ConfigException(
                "{$path} must return an array, got " . get_debug_type($contents) . '.',
            );
        }

        /** @var array<string, mixed> $contents narrows array<mixed, mixed> — return.type without it */
        return $contents;
    }

    /**
     * @param  array<string, mixed> $raw
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function definitionsFrom(array $raw): array
    {
        $definitions = [];

        foreach ($raw as $namespace => $entries) {
            if (!\is_array($entries)) {
                throw new ConfigException("Definition namespace '{$namespace}' must be an array.");
            }

            foreach ($entries as $key => $definition) {
                if (!\is_array($definition)) {
                    throw new ConfigException("Definition '@{$namespace}:{$key}' must be an array.");
                }

                /** @var array<string, mixed> $definition narrows array<mixed, mixed> — return.type without it */
                $definitions[$namespace][(string) $key] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed> $base
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $name => $value) {
            // A map is a set of named settings, so merging is the useful behaviour;
            // a list is one value, so merging two lists produces a third that
            // neither author intended -- ['url'] over ['email', 'max:255'] would
            // leave ['url', 'max:255'], which nobody wrote and null cannot remove.
            if (\is_array($value) && !array_is_list($value) && \is_array($base[$name] ?? null)) {
                /** @var array<string, mixed> $existing narrows array<mixed, mixed> — argument.type without it */
                $existing = $base[$name];
                /** @var array<string, mixed> $value narrows array<mixed, mixed> — argument.type without it */
                $base[$name] = $this->merge($existing, $value);

                continue;
            }

            $base[$name] = $value;
        }

        return $base;
    }

    /** @param list<ValidationError> $errors */
    private function report(array $errors): string
    {
        $lines = array_map(
            static fn (ValidationError $e): string => "  {$e->path}: {$e->message}",
            $errors,
        );

        return \sprintf(
            'Configuration in %s is invalid:%s%s',
            $this->directory,
            PHP_EOL,
            implode(PHP_EOL, $lines),
        );
    }
}
