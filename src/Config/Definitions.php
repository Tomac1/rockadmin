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
    /**
     * A namespace a reference already owns is refused here, in the one place
     * both readers of `defs.php` pass through.
     *
     * `isReference()` below leaves `['use' => '@action:…']` for `Resolver`, so
     * a definition namespace called `action` would load, be referenced, and
     * never expand — the definition quietly ignored, with the page rendering
     * something nobody wrote. Refusing the namespace is the honest half of
     * that decision.
     *
     * @param array<string, array<string, array<string, mixed>>> $definitions namespace => key => definition
     */
    public function __construct(private readonly array $definitions)
    {
        foreach (array_keys($definitions) as $namespace) {
            if (!\in_array(strtolower((string) $namespace), Resolver::referenceNamespaces(), true)) {
                continue;
            }

            throw new ConfigException(\sprintf(
                "Definition namespace '%s' is the name of a reference namespace, so '@%s:key' already means "
                . 'something else and a definition under it could never be expanded. The reference '
                . "namespaces are '@" . implode(":', '@", Resolver::referenceNamespaces()) . ":'.",
                $namespace,
                $namespace,
            ));
        }
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function expand(array $config): array
    {
        return $this->node($config, []);
    }

    /**
     * @param  array<string, mixed> $node
     * @param  list<string>         $chain references already being expanded
     * @return array<string, mixed>
     */
    private function node(array $node, array $chain): array
    {
        if (\array_key_exists('use', $node) && !self::isReference($node['use'])) {
            $node = $this->applyUse($node, $chain);
        }

        foreach ($node as $name => $value) {
            if (\is_array($value)) {
                /** @var array<string, mixed> $value narrows array<mixed, mixed> — argument.type without it */
                $node[$name] = $this->node($value, $chain);
            }
        }

        return $node;
    }

    /**
     * Whether this `use` names something `Resolver` owns rather than a shared
     * definition.
     *
     * Specification 8.5 writes an action adjusted where it is used as
     * `['use' => '@action:preview', 'in' => 'modal']`, inside an action
     * column's `items`. That collides head-on with this class, which runs
     * first, over the whole file, and claims every `use` key it finds — so the
     * documented form answered "Unknown definition namespace 'action'", a
     * refusal about a mechanism the page file was not using.
     *
     * It is resolved by namespace rather than by key name. `@page:`,
     * `@action:`, `@region:` and `@enum:` are references, owned by `Resolver`;
     * anything else a `use` may name is a shared definition, owned here.
     * Keying it on the namespace rather than on "a `use` inside `items`" keeps
     * this class ignorant of the page schema, which is the only reason it can
     * expand a definition anywhere in any file — and it means the two
     * mechanisms no longer have to agree by coincidence about a key name.
     *
     * The cost is that `defs.php` can no longer declare a namespace called
     * `page`, `action`, `region` or `enum`; such a definition would load and
     * never expand, so `definitionsFrom()` refuses one by name in both readers.
     */
    private static function isReference(mixed $value): bool
    {
        return \is_string($value)
            && preg_match('/^@([A-Za-z]+):/', $value, $match) === 1
            && \in_array(strtolower($match[1]), Resolver::referenceNamespaces(), true);
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
            $nearest = Schema::nearestOf(array_keys($this->definitions[$namespace]), $key);

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
}
