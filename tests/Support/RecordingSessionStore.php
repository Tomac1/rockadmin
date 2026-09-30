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

    /**
     * A stand-in for the session id a real store would reissue.
     *
     * Counting regenerations proves the call was made; this proves the thing
     * the call exists for, which is that the identifier a browser is holding
     * is not the one it held a moment ago. A test asserting session fixation
     * is closed should be able to compare the id rather than a counter.
     */
    public string $id = 'ra-session-0';

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
        $this->id = 'ra-session-' . $this->regenerations;
    }
}
