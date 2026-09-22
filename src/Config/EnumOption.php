<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** One option of a shared enumeration. */
final class EnumOption
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $color = null,
    ) {
    }

    /** @param array{value: string, label: string, color: string|null} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['value'], $data['label'], $data['color']);
    }
}
