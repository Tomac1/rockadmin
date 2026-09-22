<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * An enumeration whose options live in a table.
 *
 * `cache` is accepted because the spec describes it, but this milestone only
 * memoises within one request — which is what stops a grid of fifty rows
 * reading the table fifty times. Caching across requests needs the same store
 * as a cached row count, which arrives in milestone 10.
 */
final class EnumSource
{
    public function __construct(
        public readonly string $table,
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $order = null,
        public readonly ?int $cache = null,
    ) {
    }

    /** @param array<string, mixed> $definition */
    public static function fromConfig(string $enum, array $definition): self
    {
        foreach (['table', 'value', 'label'] as $required) {
            if (!\is_string($definition[$required] ?? null) || $definition[$required] === '') {
                throw new ConfigException(
                    "Enumeration '{$enum}' reads from the database and needs a "
                    . "non-empty '{$required}'.",
                );
            }
        }

        $order = $definition['order'] ?? null;
        $cache = $definition['cache'] ?? null;

        /** @var array{table: string, value: string, label: string} $definition */
        return new self(
            $definition['table'],
            $definition['value'],
            $definition['label'],
            \is_string($order) ? $order : null,
            \is_int($cache) ? $cache : null,
        );
    }

    /**
     * @param array{table: string, value: string, label: string, order: string|null, cache: int|null} $data
     */
    public static function __set_state(array $data): self
    {
        return new self(
            $data['table'],
            $data['value'],
            $data['label'],
            $data['order'],
            $data['cache'],
        );
    }
}
