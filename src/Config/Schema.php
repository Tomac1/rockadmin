<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** A set of configuration keys. */
final class Schema
{
    /** @param array<string, SchemaKey> $keys */
    public function __construct(private readonly array $keys)
    {
    }

    public function key(string $name): ?SchemaKey
    {
        return $this->keys[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->keys);
    }

    /**
     * The closest declared key, when one is close enough to be a likely typo.
     *
     * "lable" should suggest "label"; "x" should suggest nothing, because a
     * confident wrong guess wastes more time than no guess. The threshold is
     * half the length of what was written.
     *
     * levenshtein() compares bytes, which is right here: configuration keys
     * are snake_case ASCII by convention (spec 6.7).
     */
    public function nearest(string $name): ?string
    {
        return self::nearestOf($this->names(), $name);
    }

    /**
     * The same suggestion for names that are not a schema: definition keys,
     * enumeration keys. Without it each caller builds a throwaway Schema of
     * Mixed keys purely to reach the instance method.
     *
     * @param list<string> $candidates
     */
    public static function nearestOf(array $candidates, string $name): ?string
    {
        $best = null;
        $shortest = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $distance = levenshtein($name, $candidate);

            if ($distance < $shortest) {
                $shortest = $distance;
                $best = $candidate;
            }
        }

        return $shortest <= (int) ceil(\strlen($name) / 2) ? $best : null;
    }
}
