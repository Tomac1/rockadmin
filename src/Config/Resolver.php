<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use Closure;

/**
 * Turns the strings that carry meaning into values and objects.
 *
 * Two kinds of placeholder, separated by when they can be known:
 *
 * - `{{env.X}}` and `{{config.x}}` are fixed for a deployment, so they resolve
 *   here and are baked into the cache.
 * - `{{workspace.x}}` and `{{user.x}}` differ per request, so they become
 *   Placeholder objects that survive the cache and bind later.
 *
 * A missing environment variable resolves to null rather than raising: the
 * validator then reports it against the key that wanted a string, which names
 * the actual problem instead of naming the variable.
 */
final class Resolver
{
    private const DEFERRED = ['workspace', 'user'];

    private const IMMEDIATE = ['env', 'config'];

    private const SECRET_HINTS = ['KEY', 'SECRET', 'PASSWORD', 'TOKEN'];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * The {{config.*}} paths currently being resolved, innermost last.
     *
     * A config placeholder may point at another one, so resolution re-enters
     * itself; the chain is what turns an endless loop into a named error.
     *
     * @var list<string>
     */
    private array $chain = [];

    /**
     * @param Closure(string): ?string $env reads the environment
     * @param array<string, mixed>     $raw the merged configuration, for {{config.x}}
     */
    public function __construct(
        private readonly Closure $env,
        private readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function resolve(array $config): array
    {
        $resolved = [];

        foreach ($config as $name => $value) {
            $resolved[$name] = $this->value($value);
        }

        return $resolved;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function value(mixed $value): mixed
    {
        if (\is_array($value)) {
            /** @var array<string, mixed> $value */
            return $this->resolve($value);
        }

        if (!\is_string($value)) {
            return $value;
        }

        if (preg_match('/^@enum:(\w+)$/', $value, $match) === 1) {
            return new EnumReference($match[1]);
        }

        return $this->string($value);
    }

    private function string(string $value): mixed
    {
        if (preg_match('/^\{\{(\w+)\.([\w.]+)}}$/', $value, $match) === 1) {
            return $this->single($match[1], $match[2], $value);
        }

        return preg_replace_callback(
            '/\{\{(\w+)\.([\w.]+)}}/',
            function (array $match): string {
                if (\in_array($match[1], self::DEFERRED, true)) {
                    throw new ConfigException(
                        "{$match[0]} cannot appear inside a longer string. A "
                        . 'workspace or user placeholder becomes a bound value, '
                        . 'not text, so there is nothing to interpolate it into.',
                    );
                }

                $resolved = $this->immediate($match[1], $match[2], $match[0]);
                if (\is_scalar($resolved) || $resolved === null) {
                    return (string) ($resolved ?? '');
                }

                throw new ConfigException(
                    'Cannot interpolate ' . get_debug_type($resolved) . ' '
                    . "into string at {$match[0]}",
                );
            },
            $value,
        ) ?? $value;
    }

    private function single(string $namespace, string $name, string $original): mixed
    {
        if (\in_array($namespace, self::DEFERRED, true)) {
            return new Placeholder($namespace, $name);
        }

        return $this->immediate($namespace, $name, $original);
    }

    private function immediate(string $namespace, string $name, string $original): mixed
    {
        if (!\in_array($namespace, self::IMMEDIATE, true)) {
            throw new ConfigException(
                "Unknown placeholder namespace '{$namespace}' in {$original}. "
                . 'Use env, config, workspace or user.',
            );
        }

        if ($namespace === 'config') {
            return $this->fromConfig($name);
        }

        $this->warnIfSecret($name);

        return ($this->env)($name);
    }

    /**
     * Resolves what a {{config.x}} placeholder points at.
     *
     * The value is read from the raw, pre-resolution store — that is what makes
     * a single pass predictable — and then resolved in turn. Without that second
     * step a raw '{{workspace.site_id}}' would come back as text, which would let
     * {{config.*}} walk around the refusal to interpolate a deferred placeholder
     * into a longer string.
     */
    private function fromConfig(string $name): mixed
    {
        if (\in_array($name, $this->chain, true)) {
            $printedChain = implode(' -> ', [...$this->chain, $name]);

            throw new ConfigException("Configuration placeholder cycle: {$printedChain}.");
        }

        $this->chain[] = $name;

        try {
            return $this->value($this->fromRaw($name));
        } finally {
            array_pop($this->chain);
        }
    }

    /** Reads a dotted path out of the raw, pre-resolution configuration. */
    private function fromRaw(string $name): mixed
    {
        $value = $this->raw;

        foreach (explode('.', $name) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function warnIfSecret(string $name): void
    {
        foreach (self::SECRET_HINTS as $hint) {
            if (str_contains($name, $hint)) {
                $this->warnings[] = "{{env.{$name}}} looks like a secret. Configuration "
                    . 'values can end up rendered, so check this one is meant to be visible.';

                return;
            }
        }
    }
}
