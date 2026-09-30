<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Http\SessionStore;

/**
 * An in-memory session that counts regenerations.
 *
 * `ArraySessionStore` is final and its `regenerate()` is deliberately a no-op,
 * so proving that a privilege change reissues the session id needs a store
 * that remembers being asked.
 */
final class RecordingSessionStore implements SessionStore
{
    public int $regenerations = 0;

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
        ++$this->regenerations;
    }
}
