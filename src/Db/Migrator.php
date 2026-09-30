<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use Throwable;

/**
 * Applies the SDK's own `.sql` files and records what it applied.
 *
 * Specification 11 puts the CLI in a later milestone, so this is a class a
 * project calls — `bin/ra-migrate.php` is the one-line script that ships with
 * it, and the command will wrap the same class rather than repeat it. The DDL
 * lives in `migrations/` and is shipped with the SDK on purpose: hand-copying
 * it into each host's own migration system would fork the schema per project,
 * which is the one thing the `ra_` prefix exists to prevent.
 *
 * **Safe to run on every deploy.** A migration already named in
 * `ra_migrations` is not run again, so a second call applies nothing.
 *
 * **A failed migration is never recorded.** The ledger row is inserted only
 * after every statement of the file has succeeded. That ordering, not the
 * transaction, is what guarantees it — see below.
 *
 * **What the transaction actually protects.** On PostgreSQL a migration runs
 * inside `Connection::transaction()` and that is real: DDL is transactional
 * there, so a file that fails halfway leaves nothing behind. MySQL commits
 * implicitly before and after every DDL statement, which does not merely make
 * the transaction useless — it ends it, so the `COMMIT` afterwards raises
 * "There is no active transaction" and turns a migration that worked into one
 * that reports failure. `Dialect::transactionalDdl()` is therefore asked
 * first, and on MySQL the statements run without one. A migration that fails
 * halfway on MySQL has to be cleaned up by hand — drop what it created —
 * before it can be retried, and no amount of transaction handling here would
 * change that.
 */
final class Migrator
{
    /** The ledger is bookkeeping, so the runner creates it rather than a migration. */
    public const LEDGER = 'ra_migrations';

    private readonly string $directory;

    public function __construct(
        private readonly Connection $connection,
        ?string $directory = null,
    ) {
        $this->directory = $directory ?? \dirname(__DIR__, 2) . '/migrations';
    }

    /**
     * Applies every migration not yet recorded, in name order.
     *
     * @return list<string> the names applied by this call, in the order applied
     */
    public function migrate(): array
    {
        $this->createLedger();

        $applied = [];

        foreach ($this->pending() as $name) {
            $this->apply($name);

            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * The names on disk that are not yet in the ledger, in name order.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $this->createLedger();

        $done = array_flip($this->applied());

        return array_values(array_filter(
            $this->available(),
            static fn (string $name): bool => !isset($done[$name]),
        ));
    }

    /** @return list<string> */
    public function applied(): array
    {
        $this->createLedger();

        $names = [];

        foreach ($this->connection->select(new Sql('SELECT name FROM ' . self::LEDGER . ' ORDER BY name')) as $row) {
            $name = $row['name'];

            if (\is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Every `.sql` file in the migration directory, sorted by name.
     *
     * Name order is the whole ordering rule: `001_identity.sql` before
     * `002_...`, with no separate index file to fall out of step with the
     * directory.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $names = [];

        foreach (glob($this->directory . '/*.sql') ?: [] as $path) {
            $names[] = basename($path);
        }

        sort($names, \SORT_STRING);

        return $names;
    }

    private function apply(string $name): void
    {
        $statements = $this->statements($name);

        $run = function () use ($name, $statements): void {
            foreach ($statements as $statement) {
                $this->connection->execute(new Sql($statement));
            }

            $this->connection->execute(new Sql(
                'INSERT INTO ' . self::LEDGER . ' (name, applied_at) VALUES (?, ?)',
                [$name, gmdate('Y-m-d H:i:s')],
            ));
        };

        try {
            if ($this->connection->dialect()->transactionalDdl()) {
                $this->connection->transaction($run);
            } else {
                $run();
            }
        } catch (Throwable $e) {
            // The driver's message names a statement, never the file it came
            // from, and with several migrations pending that is the first
            // thing whoever reads this needs.
            throw new DbException(
                "Migration '{$name}' failed and was not recorded: " . $e->getMessage(),
                0,
                $e,
                $e instanceof DbException ? $e->sqlState : null,
            );
        }
    }

    /**
     * The file's statements, with `{{ddl.*}}` expanded for this server.
     *
     * Statements are separated by a semicolon at the end of a line. That is a
     * rule about the shipped files rather than a general SQL parser: none of
     * them contains a semicolon inside a literal, and a migration that needs
     * one is a reason to write the statement in PHP, not to grow a parser
     * here. `--` comment lines are dropped before splitting so that a comment
     * following the last semicolon is not mistaken for a statement.
     *
     * @return list<string>
     */
    private function statements(string $name): array
    {
        $path = $this->directory . '/' . $name;
        $text = file_get_contents($path);

        if ($text === false) {
            throw new DbException("Migration '{$name}' could not be read from {$path}.");
        }

        $text = preg_replace('/^\s*--.*$/m', '', $this->expand($text, $name)) ?? '';

        $statements = [];

        foreach (preg_split('/;\s*(?:\r?\n|$)/', $text) ?: [] as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    private function expand(string $text, string $name): string
    {
        $dialect = $this->connection->dialect();

        return preg_replace_callback(
            '/\{\{ddl\.(\w+)\}\}/',
            static function (array $match) use ($dialect, $name): string {
                try {
                    return $dialect->ddl((string) $match[1]);
                } catch (DbException $e) {
                    throw new DbException("Migration '{$name}': " . $e->getMessage(), 0, $e);
                }
            },
            $text,
        ) ?? $text;
    }

    /**
     * Creates the ledger if it is missing.
     *
     * `IF NOT EXISTS` rather than a migration of its own, because the table
     * that records which migrations ran cannot be one of them: reading it to
     * find out would require it to exist already.
     */
    private function createLedger(): void
    {
        $this->connection->execute(new Sql(\sprintf(
            'CREATE TABLE IF NOT EXISTS %s (name VARCHAR(255) NOT NULL PRIMARY KEY, applied_at %s NOT NULL)',
            self::LEDGER,
            $this->connection->dialect()->ddl('timestamp'),
        )));
    }
}
