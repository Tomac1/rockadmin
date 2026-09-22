<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A placeholder whose value is not known until a request is being served.
 *
 * `{{workspace.site_id}}` and `{{user.id}}` differ per request, so they cannot
 * be baked into the cached configuration. They survive it as objects and are
 * bound when the request is handled — which is also what keeps them out of SQL
 * text: a bound value can only ever be a value.
 */
final class Placeholder
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $name,
    ) {
    }

    public function __toString(): string
    {
        return "{{{$this->namespace}.{$this->name}}}";
    }

    /** @param array{namespace: string, name: string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['namespace'], $data['name']);
    }
}
