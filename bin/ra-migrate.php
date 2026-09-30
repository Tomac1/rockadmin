<?php

declare(strict_types=1);

/**
 * Creates or updates RockAdmin's own `ra_` tables.
 *
 * Specification 11 puts the CLI in a later milestone, so this is the whole
 * interface for now: a script thin enough that a project which would rather
 * call `Migrator` from its own deploy hook loses nothing by doing so.
 *
 *     php vendor/tomac1/rockadmin/bin/ra-migrate.php \
 *         "mysql:host=127.0.0.1;dbname=shop" root secret
 *
 * The three arguments are a PDO DSN, a user and a password; with none given
 * it reads `RA_DSN`, `RA_DB_USER` and `RA_DB_PASSWORD` from the environment
 * instead, which is what a deploy hook usually already has. It is safe to run
 * on every deploy: a migration already recorded in `ra_migrations` is not run
 * again.
 *
 * A project that already builds its own PDO — most do — should skip this and
 * call the class:
 *
 *     (new RockAdmin\Db\Migrator(RockAdmin\Db\Connection::fromPdo($pdo)))->migrate();
 */

require __DIR__ . '/../vendor/autoload.php';

use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;

/** @var list<string> $argv */
$argv = $_SERVER['argv'] ?? [];

$argument = static function (int $index, string $variable) use ($argv): ?string {
    $value = $argv[$index] ?? getenv($variable);

    return is_string($value) && $value !== '' ? $value : null;
};

$dsn = $argument(1, 'RA_DSN');

if ($dsn === null) {
    fwrite(STDERR, "Usage: ra-migrate.php <dsn> [user] [password], or set RA_DSN.\n");

    exit(1);
}

try {
    $connection = Connection::fromPdo(
        new PDO($dsn, $argument(2, 'RA_DB_USER'), $argument(3, 'RA_DB_PASSWORD')),
    );

    $applied = (new Migrator($connection))->migrate();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");

    exit(1);
}

if ($applied === []) {
    echo "Nothing to apply; the database is up to date.\n";

    exit(0);
}

foreach ($applied as $name) {
    echo 'Applied ' . $name . "\n";
}
