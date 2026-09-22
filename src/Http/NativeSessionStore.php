<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/** The default store: native PHP sessions, started on first use. */
final class NativeSessionStore implements SessionStore
{
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();

        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();

        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();

        session_regenerate_id(true);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
