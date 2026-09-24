<?php

declare(strict_types=1);

/**
 * Writes the reference documentation from the schemas.
 *
 * Run it with `composer run docs:reference`. ReferenceIsCurrentTest fails the
 * build when a committed file and its schema disagree, so adding a key
 * without running this is caught rather than noticed. What gets written is
 * listed in bin/reference-targets.php, which that test reads too.
 */

require __DIR__ . '/../vendor/autoload.php';

use RockAdmin\Config\Reference;

/** @var list<array{file: string, schema: \RockAdmin\Config\Schema, title: string, intro: string}> $targets */
$targets = require __DIR__ . '/reference-targets.php';

foreach ($targets as $target) {
    $markdown = Reference::markdown($target['schema'], $target['title'], $target['intro']);

    file_put_contents(__DIR__ . '/../docs/reference/' . $target['file'], $markdown);

    echo 'Wrote docs/reference/' . $target['file'] . ' (' . strlen($markdown) . " bytes).\n";
}
