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

    /** @param Closure(string): ?string $env */
    public function __construct(
        private readonly string $directory,
        private readonly Closure $env,
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

        $enums = $definitions->expand($this->read('enums.php'));
        $resolvedEnums = $resolver->resolve($enums);
        $this->warnings = $resolver->warnings();

        return new Config($resolved, Enums::fromConfig($resolvedEnums));
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
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

                /** @var array<string, mixed> $definition */
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
            if (\is_array($value) && \is_array($base[$name] ?? null)) {
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
