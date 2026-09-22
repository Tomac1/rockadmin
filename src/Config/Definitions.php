<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * Inlines shared fragments referenced as ['use' => '@namespace:key'].
 *
 * Repeating a column definition on nine pages is how configuration rots: the
 * tenth gets it slightly wrong and nobody notices. Expansion happens once, at
 * load, and the result is what the cache stores — so a reference costs nothing
 * at runtime.
 */
final class Definitions
{
    /** @param array<string, array<string, array<string, mixed>>> $definitions namespace => key => definition */
    public function __construct(private readonly array $definitions)
    {
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function expand(array $config): array
    {
        /** @var array<string, mixed> $expanded */
        $expanded = $this->node($config, []);

        return $expanded;
    }

    /**
     * @param  array<string, mixed> $node
     * @param  list<string>         $chain references already being expanded
     * @return array<string, mixed>
     */
    private function node(array $node, array $chain): array
    {
        if (isset($node['use'])) {
            $node = $this->applyUse($node, $chain);
        }

        foreach ($node as $name => $value) {
            if (\is_array($value)) {
                /** @var array<string, mixed> $value */
                $node[$name] = $this->node($value, $chain);
            }
        }

        return $node;
    }

    /**
     * @param  array<string, mixed> $node
     * @param  list<string>         $chain
     * @return array<string, mixed>
     */
    private function applyUse(array $node, array $chain): array
    {
        $reference = $node['use'];
        unset($node['use']);

        if (!\is_string($reference) || preg_match('/^@(\w+):([\w.]+)$/', $reference, $match) !== 1) {
            $printed = \is_string($reference) ? $reference : get_debug_type($reference);

            throw new ConfigException(
                "'{$printed}' is not a definition reference. Write '@namespace:key', "
                . "for example '@column:id'.",
            );
        }

        [, $namespace, $key] = $match;
        $step = "{$namespace}:{$key}";

        if (\in_array($step, $chain, true)) {
            $printedChain = implode(' -> ', [...$chain, $step]);

            throw new ConfigException("Definition reference cycle: {$printedChain}.");
        }

        return $this->merge($this->node($this->lookup($namespace, $key), [...$chain, $step]), $node);
    }

    /** @return array<string, mixed> */
    private function lookup(string $namespace, string $key): array
    {
        if (!isset($this->definitions[$namespace])) {
            $known = implode(', ', array_keys($this->definitions));

            throw new ConfigException(
                "Unknown definition namespace '{$namespace}'. Known namespaces: {$known}.",
            );
        }

        if (!isset($this->definitions[$namespace][$key])) {
            $nearest = (new Schema(array_map(
                static fn (): SchemaKey => new SchemaKey(ValueType::Mixed),
                $this->definitions[$namespace],
            )))->nearest($key);

            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new ConfigException("Unknown definition '@{$namespace}:{$key}'.{$suffix}");
        }

        return $this->definitions[$namespace][$key];
    }

    /**
     * Overrides win; a null override removes the inherited key entirely.
     *
     * @param  array<string, mixed> $base
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $name => $value) {
            if ($value === null) {
                unset($base[$name]);

                continue;
            }

            if (\is_array($value) && \is_array($base[$name] ?? null)) {
                /** @var array<string, mixed> $existing */
                $existing = $base[$name];
                /** @var array<string, mixed> $value */
                $base[$name] = $this->merge($existing, $value);

                continue;
            }

            $base[$name] = $value;
        }

        return $base;
    }
}
