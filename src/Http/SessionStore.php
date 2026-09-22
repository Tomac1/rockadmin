<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * Session access, kept behind an interface so RockAdmin never calls
 * session_start() in a host that manages its own sessions.
 */
interface SessionStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function forget(string $key): void;

    /** Issues a new session id, keeping the data. Called on privilege change. */
    public function regenerate(): void;
}
