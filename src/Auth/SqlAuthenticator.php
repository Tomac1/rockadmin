<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Sql;

/**
 * The default `Authenticator`: `ra_users` and `ra_login_attempts`, Argon2id,
 * and a throttle that survives a discarded cookie.
 *
 * **The throttle is a table, not the session.** Somebody guessing passwords
 * throws away cookies between attempts, so a session counter throttles only
 * the honest. It is keyed by email *and* by address, and both are checked: by
 * email alone one attacker locks out a named administrator whenever they feel
 * like it, and by address alone a botnet walks straight past it.
 *
 * **An attempt that the throttle refuses is not recorded.** That is the
 * difference between a window that drains and a lockout that an attacker can
 * hold open forever by continuing to call — and with no password reset until
 * milestone 9, a lockout nobody can wait out is a lockout nobody can undo.
 *
 * **A success clears that email's attempts, and leaves the address's alone.**
 * The failure being optimised against is an administrator who forgot their
 * password, tried six variants, remembered it, and would otherwise be two
 * mistakes from a lockout for the rest of the window. Clearing the address's
 * count as well would let anybody holding one valid account refill their
 * budget before spraying other addresses, so that half is deliberately kept.
 *
 * **A wrong password, an unknown email and a deactivated account are one
 * answer.** The same sentence and the same work: every path verifies exactly
 * one hash, against the stored one where there is one and against
 * `PasswordHasher::dummyHash()` where there is not. Returning early on "no
 * such user" would leak which addresses exist through the response time,
 * whatever the sentence said. `SqlAuthenticatorTest` asserts that both paths
 * issue the same statements and ask the hasher once.
 *
 * **Emails are folded to lower case, for the lookup and for the throttle
 * key.** MySQL's default collation compares text without regard to case and
 * PostgreSQL's does not, so `WHERE email = ?` would let an address sign in on
 * one server and not on the other — and would let `JANA@` and `jana@` keep
 * separate throttle counts on the second. That is the one behaviour rule 3
 * forbids, so the fold happens here rather than in the query planner's
 * opinion.
 */
final class SqlAuthenticator implements Authenticator
{
    /**
     * How many attempts an address and a machine get inside the window.
     *
     * This was three numbers on this constructor, and the docblock they
     * carried said they would become configuration keys "when that section is
     * written". That section is `auth.*` now and `Throttle` is what reads it,
     * so this class holds no numbers at all -- which is where they belonged:
     * how often somebody may try is a policy, and verifying a password is not.
     */
    private readonly Throttle $throttle;

    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasher $hasher = new Argon2idHasher(),
        /**
         * Omitting it means the default allowances, never *no* allowance.
         *
         * It was briefly `?Throttle $throttle = null` with null meaning "count
         * nothing", which reads as a convenience and is a fail-open default:
         * `new SqlAuthenticator($connection)` is the obvious way to build one,
         * it is what the demo and a host would write, and it would have
         * accepted unlimited guesses while looking exactly like an
         * authenticator that did not. A security control that is absent must
         * not be the thing you get by not mentioning it.
         */
        ?Throttle $throttle = null,
    ) {
        $this->throttle = $throttle ?? new Throttle($connection);
    }

    public function attempt(string $email, string $password, string $ip): LoginResult
    {
        $normalised = $this->normaliseEmail($email);

        if ($this->throttle->isExhausted(Throttle::LOGIN, $normalised, $ip)) {
            return LoginResult::throttled();
        }

        // Recorded before anything is read, and outside the transaction the
        // success writes use: the attempt is the throttle's only evidence, and
        // a later failure -- a broken row, a rolled-back stamp -- must not
        // take it with it.
        $this->throttle->record(Throttle::LOGIN, $normalised, $ip);

        $row = $this->rowByEmail($normalised);
        $stored = $row === null ? null : $row['password'];
        $hash = \is_string($stored) && $stored !== '' ? $stored : $this->hasher->dummyHash();

        // One verification on every path, before anything branches on whether
        // the row exists. See the class docblock.
        $verified = $this->hasher->verify($password, $hash);

        if (!$verified || $row === null || !$this->truthy($row['is_active'] ?? null)) {
            return LoginResult::refused();
        }

        $user = $this->userFrom($row);

        $this->connection->transaction(function () use ($user, $password, $hash): void {
            if ($this->hasher->needsRehash($hash)) {
                $this->connection->execute(new Sql(
                    'UPDATE ra_users SET password = ? WHERE id = ?',
                    [$this->hasher->hash($password), $user->id],
                ));
            }

            $this->connection->execute(new Sql(
                'UPDATE ra_users SET last_login_at = ? WHERE id = ?',
                [$this->now(), $user->id],
            ));

        });

        // After the transaction rather than inside it: clearing the budget is
        // not part of the sign-in, and a failure to clear it must not undo a
        // sign-in that worked. The cost of it failing is one wasted attempt
        // out of ten.
        $this->throttle->clear(Throttle::LOGIN, $normalised);

        return LoginResult::success($user);
    }

    /**
     * The user this id may act as now.
     *
     * A deactivated account answers null, not an `AuthUser` with a flag on it.
     * This is what a session is resolved through on every request, so anything
     * else would mean deactivating somebody has no effect until their session
     * expires — which is never, for a session they keep using.
     */
    public function byId(string $id): ?AuthUser
    {
        // `ra_users.id` is a generated BIGINT, so an id that is not a run of
        // digits cannot name a row -- and asking PostgreSQL to compare one
        // raises an invalid-input error, turning a tampered session cookie
        // into a 500 where the honest answer is "nobody".
        if (preg_match('/^\d+$/', $id) !== 1) {
            return null;
        }

        $rows = $this->connection->select(new Sql(
            'SELECT id, email, name, is_active, roles, workspaces FROM ra_users WHERE id = ?',
            [$id],
        ));

        $row = $rows[0] ?? null;

        if ($row === null || !$this->truthy($row['is_active'] ?? null)) {
            return null;
        }

        return $this->userFrom($row);
    }

    /**
     * Nothing, today — and that is an answer rather than an omission.
     *
     * The session belongs to the caller, which clears it whether or not this
     * does anything, and this class keeps no per-user state that outlives a
     * request. Specification 9.1 also promises "logout everywhere", and that
     * is the one thing that would give this method work: it needs somewhere to
     * record that every session issued before a moment is void — a
     * `sessions_valid_from` column on `ra_users`, stamped here, and a
     * comparison in `Identity` against the moment the session was created.
     * Both halves are missing, and `ra_users` has already shipped, so adding
     * the column means migration `002` rather than an edit to `001` (the
     * checksum in `Migrator` refuses that, correctly). Doing half of it here —
     * stamping a column nothing reads — would look like the feature while
     * every other session stayed live.
     */
    public function logout(AuthUser $user): void
    {
    }

    /**
     * The row for this address, or null.
     *
     * Two rows is not a case to pick a winner in. PostgreSQL's `UNIQUE
     * (email)` is case-sensitive, so it permits `Jana@example.com` beside
     * `jana@example.com` where MySQL's collation would refuse the second;
     * signing somebody into whichever came first would mean signing them into
     * an account they did not name. It is a broken installation, and it says
     * so loudly at the moment somebody is looking.
     *
     * @return ?array<string, mixed>
     */
    private function rowByEmail(string $email): ?array
    {
        $rows = $this->connection->select(new Sql(
            'SELECT id, email, name, password, is_active, roles, workspaces FROM ra_users '
                . 'WHERE LOWER(email) = ? ORDER BY id',
            [$email],
        ));

        if (\count($rows) > 1) {
            throw new DbException(
                "More than one row in ra_users matches '{$email}' once case is ignored. RockAdmin "
                . 'signs in by address without regard to case on both servers, so these rows are '
                . 'ambiguous; merge them, and add a unique index over the lower-cased address so '
                . 'PostgreSQL refuses the next one.',
            );
        }

        return $rows[0] ?? null;
    }

    /** @param array<string, mixed> $row */
    private function userFrom(array $row): AuthUser
    {
        $id = $this->text($row['id'] ?? null);

        return new AuthUser(
            $id,
            $this->text($row['email'] ?? null),
            $this->text($row['name'] ?? null),
            $this->stringList($row['roles'] ?? null, 'roles', $id),
            $this->stringList($row['workspaces'] ?? null, 'workspaces', $id),
        );
    }

    /**
     * A JSON column read as a list of strings, or refused.
     *
     * Refused rather than coerced, because every plausible coercion is worse
     * than stopping: `{"editor":true}` read as its keys, or as nothing at all,
     * produces a user with no permissions — which is indistinguishable from a
     * correct answer for a user who has none, and so would be found months
     * later by somebody wondering why a role does nothing.
     *
     * @return list<string>
     */
    private function stringList(mixed $value, string $column, string $id): array
    {
        $decoded = \is_string($value) ? json_decode($value, true) : $value;

        if (\is_array($decoded) && array_is_list($decoded)) {
            $strings = [];

            foreach ($decoded as $item) {
                if (!\is_string($item)) {
                    $strings = null;

                    break;
                }

                $strings[] = $item;
            }

            if ($strings !== null) {
                return $strings;
            }
        }

        throw new DbException(
            "ra_users.{$column} for user '{$id}' is not a JSON list of strings: "
            . var_export($value, true) . '. RockAdmin refuses it rather than reading it as an empty '
            . 'set, which would look exactly like a user who was granted nothing.',
        );
    }

    /**
     * Both servers answer a boolean differently, and the driver's own settings
     * change the answer again: PostgreSQL hands back `'t'`, MySQL a
     * `TINYINT(1)` as `1` or `'1'`. Anything not recognisably false is true,
     * except that nothing recognisable at all is false — a column this cannot
     * read must not admit somebody.
     */
    private function truthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value !== 0;
        }

        if (\is_string($value)) {
            return !\in_array(strtolower($value), ['', '0', 'f', 'false', 'n', 'no'], true);
        }

        return false;
    }

    private function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Lower-cased and trimmed, and cut to what the column holds.
     *
     * `ra_login_attempts.email` is VARCHAR(255) and the strict `sql_mode` this
     * project tests under refuses a longer value rather than truncating it. No
     * address longer than that can name a row in `ra_users` either, whose own
     * column is the same width, so cutting loses nothing but a 500 on a form
     * somebody pasted an essay into.
     */
    private function normaliseEmail(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 255);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

}
