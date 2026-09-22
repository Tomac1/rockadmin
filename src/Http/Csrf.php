<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * One token per session, compared in constant time.
 *
 * Every POST carries it; core.js sends it in a header automatically.
 */
final class Csrf
{
    private const SESSION_KEY = '_rockadmin_csrf';

    public const HEADER = 'x-csrf-token';

    public const FIELD = '_csrf';

    public function __construct(private readonly SessionStore $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!\is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        if (!\is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }
}
