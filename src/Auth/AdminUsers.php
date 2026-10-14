<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Sql;

/**
 * The `ra_users` table, for the things that are not signing in.
 *
 * `SqlAuthenticator` answers one question -- may this password sign in as this
 * address -- and deliberately nothing else. Setting a password, creating an
 * administrator and asking whether there are any yet are three different
 * questions, asked by three different parts of the admin (the reset handler,
 * `_setup`, and the administrators page), and putting them on the
 * authenticator would make the interface a project replaces in order to
 * authenticate against its own table carry four methods it has no business
 * implementing.
 *
 * So this is the SDK's own table, not a seam. A project that replaces
 * `Authenticator` keeps its own users somewhere else and does not use this;
 * one that uses the default authenticator gets this with it.
 *
 * **Addresses are folded here as everywhere else.** `ra_users_email_lower`
 * (migration 002) makes the database agree, and the fold is repeated in the
 * query because MySQL and PostgreSQL disagree about what `email = ?` means.
 */
final class AdminUsers
{
    /**
     * What the one account created by `_setup` writes into `founder`.
     *
     * A word rather than a flag, because the column is a claim and not a
     * boolean: a unique index over a boolean would permit one `false` as well
     * as one `true`, which is the opposite of what every other row needs.
     */
    public const string FOUNDER = 'yes';

    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasher $hasher = new Argon2idHasher(),
    ) {
    }

    /**
     * Whether anybody at all has an account.
     *
     * `_setup` is reachable only while this is true, and that is the whole of
     * its guard: an installation with one administrator has somebody who can
     * create the rest.
     */
    public function none(): bool
    {
        $count = $this->connection->scalar(new Sql('SELECT COUNT(*) FROM ra_users'));

        return !\is_scalar($count) || (int) $count === 0;
    }

    /**
     * The active account for this address, or null.
     *
     * Null for an address nobody has and for one that is deactivated, which
     * are the same answer on purpose: a deactivated administrator must not be
     * able to find out that their account still exists by asking for a reset
     * link, and nobody else must be able to find out that it ever did.
     *
     * @return ?array{id: string, email: string, name: string}
     */
    public function activeByEmail(string $email): ?array
    {
        $rows = $this->connection->select(new Sql(
            'SELECT id, email, name, is_active FROM ra_users WHERE LOWER(email) = ? ORDER BY id',
            [self::address($email)],
        ));

        $row = $rows[0] ?? null;

        if ($row === null || !self::truthy($row['is_active'] ?? null)) {
            return null;
        }

        return [
            'id' => self::text($row['id'] ?? null),
            'email' => self::text($row['email'] ?? null),
            'name' => self::text($row['name'] ?? null),
        ];
    }

    /**
     * How many *other* active accounts hold this role.
     *
     * The question behind "you cannot remove the last superadmin": before
     * taking the role away from somebody, deactivating them or deleting them,
     * somebody has to know whether anyone else can still reach everything. An
     * installation that locks itself out has no way back that does not involve
     * a database client.
     *
     * Decoded in PHP rather than asked of the database. MySQL and PostgreSQL
     * both have JSON containment operators and they are spelled differently,
     * so a `Dialect` difference would exist for a query over a table that
     * holds five rows. Reading them and looking is the smaller thing.
     */
    public function othersHolding(string $role, string $exceptId): int
    {
        $found = 0;

        foreach ($this->connection->select(new Sql('SELECT id, roles, is_active FROM ra_users')) as $row) {
            if (self::text($row['id'] ?? null) === $exceptId || !self::truthy($row['is_active'] ?? null)) {
                continue;
            }

            $roles = json_decode(self::text($row['roles'] ?? null), true);

            if (\is_array($roles) && \in_array($role, $roles, true)) {
                $found++;
            }
        }

        return $found;
    }

    /**
     * Writes a new password for this address, hashed.
     *
     * Answers how many rows it changed rather than nothing, because the caller
     * -- a reset that has already spent its token -- has to be able to tell
     * "set" from "that account went away while the form was open" without
     * reading the row again.
     */
    public function setPassword(string $email, string $plain): int
    {
        return $this->connection->execute(new Sql(
            'UPDATE ra_users SET password = ? WHERE LOWER(email) = ?',
            [$this->hasher->hash($plain), self::address($email)],
        ));
    }

    /**
     * Creates an administrator and returns its id.
     *
     * `$plain` is null for somebody who will be invited: the row exists, it
     * holds no password, and `SqlAuthenticator` refuses an empty hash -- so
     * the account cannot be signed into until a link is used. That is a
     * deliberate state rather than an accident, and it is why the column is
     * nullable in migration 001.
     *
     * `$founder` marks the one account `_setup` creates. A unique index
     * (migration 004) permits exactly one row to carry it, so two people
     * setting up the same fresh installation at the same moment end with one
     * administrator rather than two -- the second insert is refused by the
     * database, at the moment it happens, with no lock taken and no isolation
     * level to reason about. Everybody else writes NULL, and both servers
     * permit any number of those in a unique index.
     *
     * @param  list<string> $roles
     * @param  list<string> $workspaces
     * @throws DbException when the address is already taken, or when another
     *                     founder exists -- both refused by an index rather
     *                     than checked for first, because checking first is a
     *                     race and an index is not
     */
    public function create(
        string $email,
        string $name,
        ?string $plain,
        array $roles,
        array $workspaces,
        bool $founder = false,
    ): string {
        $bindings = [
            self::address($email),
            $plain === null ? null : $this->hasher->hash($plain),
            $name,
            true,
            self::json($roles),
            self::json($workspaces),
            gmdate('Y-m-d H:i:s'),
            $founder ? self::FOUNDER : null,
        ];

        $insert = 'INSERT INTO ra_users (email, password, name, is_active, roles, workspaces, '
            . 'created_at, founder) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

        // The same split `SqlWriteHandler` makes, and for the same reason:
        // PostgreSQL hands the generated key back from the INSERT itself,
        // MySQL has no RETURNING clause and is asked afterwards.
        $returning = $this->connection->dialect()->returningClause('id');

        if ($returning !== null) {
            $rows = $this->connection->select(new Sql($insert . $returning, $bindings));

            return self::text($rows[0]['id'] ?? null);
        }

        $this->connection->execute(new Sql($insert, $bindings));

        return $this->connection->lastInsertId();
    }

    /** @param list<string> $values */
    private static function json(array $values): string
    {
        return json_encode(array_values($values), JSON_THROW_ON_ERROR);
    }

    private static function address(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 255);
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * PostgreSQL hands a boolean back as `'t'`, MySQL a `TINYINT(1)` as `1` or
     * `'1'`. Anything not recognisably true is false, which is the safe
     * direction for a column that decides whether an account is usable.
     */
    private static function truthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \in_array($value, [1, '1', 't', 'true', 'TRUE'], true);
    }
}
