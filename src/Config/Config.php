<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** A loaded, validated configuration. */
final class Config
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly array $data,
        private readonly Enums $enums,
    ) {
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

    /** @param array{data: array<string, mixed>, enums: Enums} $state written by var_export() */
    public static function __set_state(array $state): self
    {
        return new self($state['data'], $state['enums']);
    }
}
