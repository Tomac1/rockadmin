<?php

declare(strict_types=1);

/**
 * Creates and fills the fixture tables the demo's `ads` page reads from.
 *
 * Same tables, same columns, same driver detection as
 * `tests/Support/DatabaseTestCase.php` — `ra_test_companies`, `ra_test_users`,
 * `ra_test_ads` and `ra_test_tags` — plus one column that base fixture does
 * not need for a unit test but this page's worked example does:
 * `ra_test_ads.created_at`, for the sortable date column. Running the test
 * suite afterwards is still safe: `DatabaseTestCase::createFixtures()` drops
 * and recreates every one of these tables before each test that needs them,
 * so it never sees this script's extra column or its extra rows.
 *
 * Run it once, against whichever server is configured:
 *
 *     php demo/seed.php
 *
 * It reads the same `RA_TEST_MYSQL_*` / `RA_TEST_PGSQL_*` variables the test
 * suite does, seeds every server it finds configured, and says plainly when
 * it finds none — the same honesty `demo/index.php` shows on the page itself
 * when nothing is configured.
 */

require __DIR__ . '/../vendor/autoload.php';

use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;

/** @return array{dsn: string, user: ?string, password: ?string}|null */
function seedServerConfig(string $prefix): ?array
{
    $dsn = getenv("RA_TEST_{$prefix}_DSN");

    if (!is_string($dsn) || $dsn === '') {
        return null;
    }

    $user = getenv("RA_TEST_{$prefix}_USER");
    $password = getenv("RA_TEST_{$prefix}_PASSWORD");

    return [
        'dsn' => $dsn,
        'user' => is_string($user) ? $user : null,
        'password' => is_string($password) ? $password : null,
    ];
}

/** @param array{dsn: string, user: ?string, password: ?string} $server */
function seedOneServer(string $label, array $server): void
{
    $pdo = new PDO($server['dsn'], $server['user'], $server['password']);
    $connection = Connection::fromPdo($pdo);
    $json = $connection->dialect()->name() === 'mysql' ? 'JSON' : 'JSONB';

    foreach (['ra_test_tags', 'ra_test_ads', 'ra_test_users', 'ra_test_companies'] as $table) {
        $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
    }

    foreach ([
        'CREATE TABLE ra_test_companies (id INTEGER PRIMARY KEY, name VARCHAR(100) NOT NULL)',
        'CREATE TABLE ra_test_users (id INTEGER PRIMARY KEY, company_id INTEGER, '
            . 'name VARCHAR(100) NOT NULL, email VARCHAR(100))',
        'CREATE TABLE ra_test_ads (id INTEGER PRIMARY KEY, user_id INTEGER, '
            . "title VARCHAR(200) NOT NULL, price INTEGER, state VARCHAR(20), stats {$json}, "
            . 'created_at TIMESTAMP)',
        'CREATE TABLE ra_test_tags (id INTEGER PRIMARY KEY, ad_id INTEGER, label VARCHAR(50))',
    ] as $ddl) {
        $connection->execute(new Sql($ddl));
    }

    foreach ([
        ['INSERT INTO ra_test_companies (id, name) VALUES (?, ?)', [1, 'Velo s.r.o.']],
        ['INSERT INTO ra_test_companies (id, name) VALUES (?, ?)', [2, 'Moto a.s.']],
        ['INSERT INTO ra_test_users (id, company_id, name, email) VALUES (?, ?, ?, ?)',
            [1, 1, 'Jana', 'jana@example.com']],
        ['INSERT INTO ra_test_users (id, company_id, name, email) VALUES (?, ?, ?, ?)',
            [2, 2, 'Petr', 'petr@example.com']],
    ] as [$text, $bindings]) {
        $connection->execute(new Sql($text, $bindings));
    }

    $states = ['active', 'active', 'draft', 'active', 'sold'];
    $titles = [
        'Horské kolo', 'Silniční kolo', 'Skútr', 'Elektrokolo', 'Koloběžka',
        'Dětské kolo', 'Trekové kolo', 'BMX', 'Motorka Honda', 'Přívěsný vozík',
        'Cyklopočítač', 'Helma Giro',
    ];

    $adId = 1;
    $tagId = 1;

    foreach ($titles as $index => $title) {
        $userId = $index % 2 === 0 ? 1 : 2;
        $price = 5000 + $index * 1750;
        $state = $states[$index % count($states)];
        $stats = json_encode(['daily' => ['views' => 3 + $index * 7]], JSON_THROW_ON_ERROR);
        $createdAt = (new DateTimeImmutable('-' . $index . ' days'))->format('Y-m-d H:i:s');

        $connection->execute(new Sql(
            'INSERT INTO ra_test_ads (id, user_id, title, price, state, stats, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$adId, $userId, $title, $price, $state, $stats, $createdAt],
        ));

        foreach (array_slice(['bazar', 'sleva', 'novinka', 'doprava-zdarma'], 0, $index % 3) as $tagLabel) {
            $connection->execute(new Sql(
                'INSERT INTO ra_test_tags (id, ad_id, label) VALUES (?, ?, ?)',
                [$tagId++, $adId, $tagLabel],
            ));
        }

        $adId++;
    }

    echo "Seeded {$label}: " . count($titles) . " ads.\n";
}

$found = false;

foreach (['mysql' => 'MYSQL', 'pgsql' => 'PGSQL'] as $label => $prefix) {
    $server = seedServerConfig($prefix);

    if ($server !== null) {
        $found = true;
        seedOneServer($label, $server);
    }
}

if (!$found) {
    echo 'No database configured. Set RA_TEST_MYSQL_DSN (and, optionally, RA_TEST_PGSQL_DSN) '
        . "before running this script -- the same variables the test suite reads.\n";
    exit(1);
}
