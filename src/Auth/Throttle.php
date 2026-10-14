<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;

/**
 * How many times this address, and this machine, have tried lately.
 *
 * It was inline in `SqlAuthenticator` until password reset needed the same
 * counting. Two copies of "count the rows, compare to a number, insert one"
 * would have been two places to get the window wrong, and the kind of wrong
 * that nobody notices: a throttle that counts the wrong rows still looks like
 * a throttle.
 *
 * **Two budgets, both checked.** By address, so one account cannot be
 * hammered; by IP, so one machine cannot work through a list of addresses.
 * The IP allowance is the larger of the two because one office address carries
 * every administrator behind it, and one confused colleague must not lock out
 * the rest.
 *
 * **Both counts are always taken**, rather than returning as soon as one is
 * over. The two statements run on every path, which is one fewer way for the
 * response time to say something about which of them tripped -- or about
 * whether the address exists.
 *
 * **A kind per budget.** `login` and `password_reset` count separately,
 * because sharing would mean ten forgotten passwords exhausting the login
 * allowance -- and, worse, a login lockout blocking the reset request, which
 * removes the one self-service way out of exactly the situation somebody is
 * stuck in.
 *
 * **Nothing here prunes.** `ra_login_attempts` grows, and a row outside the
 * window costs a comparison rather than a decision. Deleting old rows is an
 * environment job and belongs to the CLI milestone; it is recorded as debt
 * rather than done quietly here, where the only safe moment to do it would be
 * during somebody's sign-in.
 */
final class Throttle
{
    /** Somebody trying to sign in. */
    public const string LOGIN = 'login';

    /** Somebody asking for a password-reset link. */
    public const string PASSWORD_RESET = 'password_reset';

    public function __construct(
        private readonly Connection $connection,
        /** How many attempts one address may make inside the window. */
        private readonly int $perEmail = 10,
        /** How many one machine may make, counting every address it tried. */
        private readonly int $perIp = 20,
        private readonly int $windowSeconds = 900,
    ) {
    }

    /**
     * Whether this attempt is over either allowance.
     *
     * Asked before the attempt is recorded, so the allowance is the number of
     * attempts rather than the number of attempts minus one.
     */
    public function isExhausted(string $kind, string $email, string $ip): bool
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $this->windowSeconds);

        $byEmail = $this->count(new Sql(
            'SELECT COUNT(*) FROM ra_login_attempts WHERE kind = ? AND email = ? AND at >= ?',
            [$kind, self::address($email), $cutoff],
        ));

        $byIp = $this->count(new Sql(
            'SELECT COUNT(*) FROM ra_login_attempts WHERE kind = ? AND ip = ? AND at >= ?',
            [$kind, self::machine($ip), $cutoff],
        ));

        return $byEmail >= $this->perEmail || $byIp >= $this->perIp;
    }

    /**
     * Writes the attempt down.
     *
     * Called before the attempt is evaluated and outside whatever transaction
     * a success goes on to use: the row is the throttle's only evidence, and a
     * later failure -- a broken row, a rolled-back stamp -- must not take it
     * with it.
     */
    public function record(string $kind, string $email, string $ip): void
    {
        $this->connection->execute(new Sql(
            'INSERT INTO ra_login_attempts (kind, email, ip, at) VALUES (?, ?, ?, ?)',
            [$kind, self::address($email), self::machine($ip), gmdate('Y-m-d H:i:s')],
        ));
    }

    /**
     * Forgets this address's attempts at one thing, which is what success
     * means.
     *
     * Only that address and only that kind: signing in successfully says
     * nothing about whoever else shares the IP, and clearing their budget too
     * would make the IP allowance avoidable by signing in once.
     */
    public function clear(string $kind, string $email): void
    {
        $this->connection->execute(new Sql(
            'DELETE FROM ra_login_attempts WHERE kind = ? AND email = ?',
            [$kind, self::address($email)],
        ));
    }

    /** How long somebody has to wait, in minutes, for a message that says so. */
    public function windowMinutes(): int
    {
        return (int) ceil($this->windowSeconds / 60);
    }

    /**
     * The address as the throttle counts it: folded and trimmed, so that
     * `Jana@Example.com ` and `jana@example.com` share one budget rather than
     * two.
     */
    private static function address(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 255);
    }

    /** `ra_login_attempts.ip` is VARCHAR(45) — long enough for IPv6, and no longer. */
    private static function machine(string $ip): string
    {
        return mb_substr(trim($ip), 0, 45);
    }

    private function count(Sql $sql): int
    {
        $value = $this->connection->scalar($sql);

        return \is_scalar($value) ? (int) $value : 0;
    }
}
