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

            if (isset($definition['source'])) {
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
     * @param  array<int|string, mixed>   $definition
     * @return array<string, EnumOption>
     */
    private static function parseOptions(string $enum, array $definition): array
    {
        $options = [];

        foreach ($definition as $value => $option) {
            $path = "{$enum}.{$value}";

            if (!\is_array($option) || !isset($option['label']) || !\is_string($option['label'])) {
                throw new ConfigException("Enumeration option '{$path}' needs a string label.");
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
            $nearest = (new Schema(array_map(
                static fn (): SchemaKey => new SchemaKey(ValueType::Mixed),
                $this->enums,
            )))->nearest($key);

            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new ConfigException("Unknown enumeration '@enum:{$key}'.{$suffix}");
        }

        return $this->enums[$key];
    }
}
