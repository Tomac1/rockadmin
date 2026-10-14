<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;

/**
 * Single-use links for setting a password, issued and spent.
 *
 * **The database never holds a working token.** The column is called
 * `token_hash` and that is what goes in it: the token itself exists in the
 * mail and in the URL the person clicks, and nowhere else. A database that
 * leaks -- a backup on a laptop, a read-only replica somebody could reach, an
 * SQL injection in the project's own code -- must not hand over a set of
 * working reset links for every administrator.
 *
 * SHA-256 rather than Argon2id, and that is deliberate rather than careless:
 * the thing being hashed is 32 bytes from `random_bytes()`, not something a
 * person chose. There is no dictionary to try and no password to stretch
 * against, so the slow hash would buy nothing and cost a tenth of a second on
 * a page anybody can request.
 *
 * **Claiming is one statement.** `UPDATE ... WHERE token_hash = ? AND used_at
 * IS NULL AND expires_at > ?` either affects a row or it does not, so two
 * clicks on the same link race in the database rather than in PHP -- where
 * "read, check, write" would let both pass the check before either wrote.
 *
 * **Issuing invalidates whatever was outstanding for that address.** One live
 * link per administrator: the newest is the one the person is looking at, and
 * an older one sitting in an older mail is one more thing that can be
 * forwarded, copied into a ticket, or found later. The cost is somebody who
 * asks twice and then clicks the first mail; they ask again, which is the
 * thing they already know how to do.
 */
final class PasswordResets
{
    public function __construct(
        private readonly Connection $connection,
        /** From `auth.reset_lifetime_minutes`, or `auth.invite_lifetime_minutes` for an invitation. */
        private readonly int $lifetimeMinutes = 60,
    ) {
    }

    /**
     * A new link for this address, returning the token that goes in the URL.
     *
     * The caller has already decided that this address belongs to somebody:
     * this writes a row for whatever it is given, because the decision about
     * whether an address exists is the one that must not be visible from
     * outside, and making it here would mean this method's own behaviour
     * answered it.
     */
    public function issue(string $email): string
    {
        $address = self::address($email);
        $token = bin2hex(random_bytes(32));

        $this->connection->transaction(function () use ($address, $token): void {
            // Spent rather than deleted: a used row is a record that a link
            // was issued and used, which is the question asked when somebody
            // says they did not ask for one.
            $this->connection->execute(new Sql(
                'UPDATE ra_password_resets SET used_at = ? WHERE email = ? AND used_at IS NULL',
                [self::now(), $address],
            ));

            $this->connection->execute(new Sql(
                'INSERT INTO ra_password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)',
                [$address, self::hash($token), self::expiry($this->lifetimeMinutes)],
            ));
        });

        return $token;
    }

    /**
     * Spends the token and answers whose it was, or null.
     *
     * Null for an unknown token, an expired one and one already used: three
     * different facts, one answer, because telling them apart tells whoever is
     * guessing which of their guesses was once real.
     */
    public function claim(string $token): ?string
    {
        $hash = self::hash($token);

        $spent = $this->connection->execute(new Sql(
            'UPDATE ra_password_resets SET used_at = ? WHERE token_hash = ? AND used_at IS NULL '
                . 'AND expires_at > ?',
            [self::now(), $hash, self::now()],
        ));

        if ($spent !== 1) {
            return null;
        }

        $email = $this->connection->scalar(new Sql(
            'SELECT email FROM ra_password_resets WHERE token_hash = ?',
            [$hash],
        ));

        return \is_scalar($email) ? (string) $email : null;
    }

    /**
     * Whether this token would be claimed, without claiming it.
     *
     * The form behind a link is drawn before anything is spent: a GET that
     * consumed the token would mean a mail client's link preview, a security
     * scanner or a double-click burned it before the person had typed
     * anything.
     */
    public function isLive(string $token): bool
    {
        $found = $this->connection->scalar(new Sql(
            'SELECT COUNT(*) FROM ra_password_resets WHERE token_hash = ? AND used_at IS NULL '
                . 'AND expires_at > ?',
            [self::hash($token), self::now()],
        ));

        return \is_scalar($found) && (int) $found > 0;
    }

    /**
     * Spends everything outstanding for an address.
     *
     * Called when a password is set by any other route -- somebody changing
     * their own, an administrator setting one for them. A link that was in
     * flight when that happened is a link nobody is expecting any more.
     */
    public function invalidateAll(string $email): void
    {
        $this->connection->execute(new Sql(
            'UPDATE ra_password_resets SET used_at = ? WHERE email = ? AND used_at IS NULL',
            [self::now(), self::address($email)],
        ));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Folded, because that is how `ra_users` is looked up and the two must agree. */
    private static function address(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 255);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private static function expiry(int $minutes): string
    {
        return gmdate('Y-m-d H:i:s', time() + ($minutes * 60));
    }
}
