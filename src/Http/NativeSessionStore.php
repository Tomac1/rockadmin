<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use InvalidArgumentException;

/**
 * The default store: native PHP sessions, started on first use.
 *
 * **It starts the session with its own cookie settings**, not with whatever
 * php.ini says. A bare `session_start()` gives `PHPSESSID` on path `/`,
 * readable by script, sent over plain HTTP, and with `use_strict_mode` off so
 * PHP accepts any session id a link hands it. That cookie is a superadmin's
 * login. So:
 *
 * - its own name (`rockadmin` by default), so it does not share a session
 *   with the site the admin is mounted on;
 * - the path of the mount point, so the rest of the domain never sees it;
 * - `HttpOnly`, so a script injected anywhere on the domain cannot read it;
 * - `Secure` whenever the request arrived over HTTPS;
 * - `SameSite=Lax` by default (`Strict` is configurable): CSRF tokens are the
 *   main defence, and this is a second one;
 * - `use_strict_mode` and `use_only_cookies`, so an id that PHP did not issue,
 *   or one that arrived in a URL, is refused and replaced.
 *
 * A session the host already started is used as it is. The host then owns
 * its cookie, and changing the settings of a running session is not possible
 * anyway.
 */
final class NativeSessionStore implements SessionStore
{
    /** @var list<string> */
    public const array SAME_SITE = ['Lax', 'Strict'];

    public function __construct(
        private readonly string $name = 'rockadmin',
        private readonly string $path = '/',
        private readonly string $sameSite = 'Lax',
        private readonly bool $secure = false,
    ) {
        if (!\in_array($sameSite, self::SAME_SITE, true)) {
            throw new InvalidArgumentException(
                "session.same_site is '{$sameSite}'. It is 'Lax' or 'Strict'; 'None' would send the "
                . 'admin session with requests from any other site.',
            );
        }

        if ($name === '' || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1 || ctype_digit($name)) {
            throw new InvalidArgumentException(
                "session.name is '{$name}'. A session cookie name is letters, digits and underscores, "
                . 'and not only digits: rockadmin_session.',
            );
        }
    }

    /**
     * Whether this request arrived over HTTPS, as far as the server can tell.
     *
     * `X-Forwarded-Proto` is honoured. Normally a forwarded header cannot be
     * trusted, but here the only thing a forged one can do is mark the
     * forger's own cookie Secure, which harms nobody but them.
     *
     * @param array<mixed> $server `$_SERVER`, whose keys PHP does not promise are strings
     */
    public static function isHttps(array $server): bool
    {
        $https = $server['HTTPS'] ?? '';

        if (\is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        $forwarded = $server['HTTP_X_FORWARDED_PROTO'] ?? '';

        return \is_string($forwarded) && strtolower(trim(explode(',', $forwarded)[0])) === 'https';
    }

    /** @return array<string, bool|int|string> what `start()` hands to session_start() */
    public function options(): array
    {
        return [
            'name' => $this->name,
            'cookie_path' => $this->path === '' ? '/' : $this->path,
            'cookie_httponly' => true,
            'cookie_secure' => $this->secure,
            'cookie_samesite' => $this->sameSite,
            'cookie_lifetime' => 0,
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
        ];
    }

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
            session_start($this->options());
        }
    }
}
