<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/** In-memory session, for tests and for the CLI, where there is no session. */
final class ArraySessionStore implements SessionStore
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        // Nothing to regenerate without a real session id.
    }
}
