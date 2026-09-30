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
 *
 * **An applied migration that has been edited is refused, not re-applied.**
 * The ledger stores a checksum of each file alongside its name, and every run
 * checks it. Without that, changing a migration after it has been applied
 * somewhere is silent: the name is recorded, so the file is never read again,
 * and two installations from the same commit end up holding different schemas
 * — the exact failure that shipping the DDL with the SDK exists to prevent.
 * The fix for an edited migration is a new migration, never a change to an old
 * one, and the exception says so. Nothing is repaired automatically and
 * nothing continues with a warning, because a schema that quietly disagrees
 * with its ledger is the thing being prevented.
 *
 * There is no installation whose `ra_migrations` predates the checksum column,
 * because nothing has shipped yet — so there is no compatibility path here,
 * and writing one would be code for a case that cannot exist. It is worth
 * knowing that this was the last free moment to add such a column: the ledger
 * is created with `IF NOT EXISTS`, which does nothing to a table that already
 * exists, so a column added to it after release needs special-case code in
 * this class rather than a migration.
 *
 * **There is no rollback, and that is a decision rather than an omission.**
 * MySQL commits DDL implicitly, so an undo that claimed to reverse a migration
 * would be a lie on one of the two servers this project supports. The way back
 * is a new migration that does the reverse, which is also the only way back
 * that leaves the ledger telling the truth.
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
        $this->verifyChecksums();

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
        return array_keys($this->ledger());
    }

    /**
     * Refuses an applied migration whose file has changed since.
     *
     * Public so that the diagnostics page can ask, and report the answer,
     * without running a migration. `migrate()` calls it before applying
     * anything: a run that would apply `002` while `001` has been edited
     * underneath it has already lost, and should say so rather than add to the
     * damage first.
     *
     * A recorded migration with no file on disk is passed over rather than
     * refused. That is a different fact — the ledger knowing more than the disk
     * is what a downgrade of the SDK looks like — and refusing it here would
     * describe a downgrade as an edit, sending whoever read the message to look
     * for a change nobody made.
     */
    public function verifyChecksums(): void
    {
        $this->createLedger();

        $available = array_flip($this->available());

        foreach ($this->ledger() as $name => $recorded) {
            if (!isset($available[$name]) || $recorded === $this->checksum($name)) {
                continue;
            }

            throw new DbException(\sprintf(
                "Migration '%s' has been edited since it was applied: the file no longer matches the "
                    . 'checksum recorded in %s. An applied migration must never be changed, because an '
                    . 'installation that has already run it will never read it again -- the two would '
                    . 'hold different schemas from the same commit. Put the change in a new migration '
                    . 'and restore this file.',
                $name,
                self::LEDGER,
            ));
        }
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
                'INSERT INTO ' . self::LEDGER . ' (name, checksum, applied_at) VALUES (?, ?, ?)',
                [$name, $this->checksum($name), gmdate('Y-m-d H:i:s')],
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
        $text = preg_replace('/^\s*--.*$/m', '', $this->expand($this->read($name), $name)) ?? '';

        $statements = [];

        foreach (preg_split('/;\s*(?:\r?\n|$)/', $text) ?: [] as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * The file's contents, with line endings normalised to LF.
     *
     * The normalisation is what the checksum is taken over, so a checkout that
     * landed CRLF — `.gitattributes` asks for LF, but a zip download or a
     * badly configured client can still do it — does not read as an edited
     * migration. A change of line endings is not a change of schema.
     */
    private function read(string $name): string
    {
        $path = $this->directory . '/' . $name;
        $text = file_get_contents($path);

        if ($text === false) {
            throw new DbException("Migration '{$name}' could not be read from {$path}.");
        }

        return str_replace("\r\n", "\n", $text);
    }

    /**
     * The file as committed, hashed — before `{{ddl.*}}` expansion, so that
     * the same file checksums identically on both servers and an installation
     * that moves from one to the other is not told its migrations were edited.
     */
    private function checksum(string $name): string
    {
        return hash('sha256', $this->read($name));
    }

    /**
     * The ledger, name to recorded checksum, in name order.
     *
     * @return array<string, string>
     */
    private function ledger(): array
    {
        $this->createLedger();

        $rows = [];

        foreach ($this->connection->select(
            new Sql('SELECT name, checksum FROM ' . self::LEDGER . ' ORDER BY name'),
        ) as $row) {
            $name = $row['name'];
            $checksum = $row['checksum'] ?? null;

            if (\is_string($name)) {
                $rows[$name] = \is_string($checksum) ? $checksum : '';
            }
        }

        return $rows;
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
     *
     * `checksum` holds a SHA-256 of the file as committed — 64 hexadecimal
     * characters. See the class docblock for why the column is here from the
     * first release rather than added by a later one.
     */
    private function createLedger(): void
    {
        $this->connection->execute(new Sql(\sprintf(
            'CREATE TABLE IF NOT EXISTS %s (name VARCHAR(255) NOT NULL PRIMARY KEY, '
                . 'checksum VARCHAR(64) NOT NULL, applied_at %s NOT NULL)',
            self::LEDGER,
            $this->connection->dialect()->ddl('timestamp'),
        )));
    }
}
