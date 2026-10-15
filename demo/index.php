<?php

declare(strict_types=1);

/**
 * The demo application. Run it with:
 *
 *     php -S localhost:8080 -t demo demo/index.php
 *
 * It was 801 lines until milestone 14, and every one of them was composition
 * a real project would have had to copy. What is left is the three things a
 * host genuinely supplies: where its configuration is, how it reaches its
 * database, and its own write logic. Everything else — fifteen handlers, the
 * gate, the menu, the storage, the mailer, the audit log — is `Admin::boot()`,
 * and a project that adds a page adds no lines here at all.
 *
 * The grid needs a database; the shell does not. Cloning this repository and
 * running the demo with nothing configured still shows a themed admin: the
 * dashboard draws, and the pages that need rows say so on the admin's own
 * error page rather than throwing a connection error before the first byte.
 */

require __DIR__ . '/../vendor/autoload.php';

use RockAdmin\Demo\ArchiveAd;
use RockAdmin\RockAdmin;
use RockAdmin\Services;
use RockAdmin\Setup;

/**
 * Whichever fixture database the environment names.
 *
 * The same `RA_TEST_MYSQL_*` / `RA_TEST_PGSQL_*` variables the test suite
 * reads and `demo/seed.php` fills. How a project reaches its database is host
 * wiring — this is what the `pdo` argument is for, and returning null is how
 * a host says it has none today.
 */
function demoPdo(): ?PDO
{
    foreach (['MYSQL', 'PGSQL'] as $prefix) {
        $dsn = getenv("RA_TEST_{$prefix}_DSN");

        if (!is_string($dsn) || $dsn === '') {
            continue;
        }

        $user = getenv("RA_TEST_{$prefix}_USER");
        $password = getenv("RA_TEST_{$prefix}_PASSWORD");

        try {
            return new PDO($dsn, is_string($user) ? $user : null, is_string($password) ? $password : null);
        } catch (PDOException) {
            continue;
        }
    }

    return null;
}

RockAdmin::serve(new Setup(
    basePath: __DIR__,
    configPath: __DIR__ . '/config',
    pdo: demoPdo(...),
    // The project's own write logic, by the name its pages use: `ads.php`
    // archives through `@handler:archive_ad` instead of a `set`, and this is
    // what that name means. It is a closure because an operation is built out
    // of what the boot produces — this one needs the flash bag.
    operations: static fn (Services $services): array => [
        'archive_ad' => new ArchiveAd($services->flashes),
    ],
));
