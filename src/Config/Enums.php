<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * Shared enumerations, defined once and referenced as @enum:key.
 *
 * One definition feeds a filter, a form select and a badge colour, so the
 * three cannot drift apart.
 */
final class Enums
{
    /** @param array<string, array<string, EnumOption>> $enums */
    private function __construct(private readonly array $enums)
    {
    }

    /** @param array<string, mixed> $enums the contents of enums.php */
    public static function fromConfig(array $enums): self
    {
        $parsed = [];

        foreach ($enums as $key => $definition) {
            if (!\is_array($definition)) {
                throw new ConfigException(
                    "Enumeration '{$key}' must be an array of options, got "
                    . get_debug_type($definition) . '.',
                );
            }

            // A database-backed definition is nothing but its source array, so an
            // option that happens to be keyed 'source' is not mistaken for one.
            if (self::isDatabaseBacked($definition)) {
                throw new ConfigException(
                    "Enumeration '{$key}' reads its options from the database, which "
                    . 'arrives in milestone 3. Until then, list the options here.',
                );
            }

            $parsed[(string) $key] = self::parseOptions((string) $key, $definition);
        }

        return new self($parsed);
    }

    /** @param array{enums: array<string, array<string, EnumOption>>} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['enums']);
    }

    /**
     * A database-backed definition is a lone 'source' entry and nothing else.
     *
     * An enumeration may legitimately have an option keyed 'source' -- a list of
     * where something came from -- and that must not be diagnosed as a milestone 3
     * feature. Requiring it to be the only entry tells the two apart. A null
     * source is still database-backed: it is a source somebody started writing
     * and left empty, and reading it as an option would name the wrong problem.
     *
     * @param array<int|string, mixed> $definition
     */
    private static function isDatabaseBacked(array $definition): bool
    {
        return \count($definition) === 1
            && \array_key_exists('source', $definition)
            && (\is_array($definition['source']) || $definition['source'] === null);
    }

    /**
     * @param  array<int|string, mixed>   $definition
     * @return array<string, EnumOption>
     */
    private static function parseOptions(string $enum, array $definition): array
    {
        $options = [];

        foreach ($definition as $value => $option) {
            $path = "{$enum}.{$value}";

            if (!\is_array($option) || !isset($option['label']) || !\is_string($option['label'])) {
                throw new ConfigException(
                    "Enumeration option '{$path}' needs a string label. A "
                    . '{{workspace.*}} or {{user.*}} placeholder cannot be one: it is not '
                    . 'known until a request, and the option list is built at load.',
                );
            }

            $color = $option['color'] ?? null;

            if ($color !== null && !\is_string($color)) {
                throw new ConfigException("Enumeration option '{$path}' has a non-string color.");
            }

            $options[(string) $value] = new EnumOption((string) $value, $option['label'], $color);
        }

        return $options;
    }

    public function has(string $key): bool
    {
        return isset($this->enums[$key]);
    }

    /** @return array<string, EnumOption> */
    public function options(EnumReference|string $enum): array
    {
        $key = $enum instanceof EnumReference ? $enum->key : $enum;

        if (!isset($this->enums[$key])) {
            $nearest = Schema::nearestOf(array_keys($this->enums), $key);

            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new ConfigException("Unknown enumeration '@enum:{$key}'.{$suffix}");
        }

        return $this->enums[$key];
    }
}
