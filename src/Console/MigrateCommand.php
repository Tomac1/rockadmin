<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;

/**
 * Creates or updates RockAdmin's own `ra_` tables.
 *
 * Safe on every deploy: a migration recorded in `ra_migrations` is not
 * applied again, and one whose file has changed since is refused rather than
 * silently re-run.
 *
 * **It is not transactional on MySQL and cannot be.** MySQL commits
 * implicitly on DDL, which ends the transaction outright — the `COMMIT` that
 * follows then raises "there is no active transaction" and a migration that
 * applied correctly reports failure. What keeps the ledger honest is the
 * order instead: the row is written only after every statement in a file has
 * succeeded, so a failure never records itself. The consequence, said rather
 * than hidden: a migration that fails halfway on MySQL leaves what it already
 * created, and that has to be tidied by hand before a retry. PostgreSQL rolls
 * back, which is why the dialect is asked before anything is wrapped.
 */
final class MigrateCommand implements Command
{
    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return "Creates or updates RockAdmin's own ra_ tables. Safe to run every deploy.";
    }

    public function run(Input $input, Output $output): int
    {
        $connection = Bootstrap::admin($input)->services->connection;

        if (!$connection instanceof Connection) {
            throw new ConsoleException(
                'There is no database to migrate. Either database.dsn in rockadmin.php is empty, '
                . 'or the bootstrap handed over no connection, or the one it handed over could not '
                . 'be opened. Run `rockadmin doctor` for which of the three it is.',
            );
        }

        $migrator = new Migrator($connection);
        $pending = $migrator->pending();

        if ($pending === []) {
            $output->success('Nothing to apply; the database is up to date.');

            return 0;
        }

        if ($input->has('dry-run')) {
            foreach ($pending as $name) {
                $output->heading('-- ' . $name);

                foreach ($migrator->sqlFor($name) as $statement) {
                    $output->line($statement . ';');
                }

                $output->line();
            }

            $output->dim(\count($pending) . ' migration(s) would be applied. Nothing was changed.');

            return 0;
        }

        foreach ($migrator->migrate() as $name) {
            $output->success('Applied ' . $name);
        }

        return 0;
    }
}
