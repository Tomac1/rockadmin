<?php

declare(strict_types=1);

/**
 * Writes docs/reference/configuration.md from the schema.
 *
 * Run it with `composer run docs:reference`. ReferenceIsCurrentTest fails the
 * build when the committed file and the schema disagree, so adding a key
 * without running this is caught rather than noticed.
 */

require __DIR__ . '/../vendor/autoload.php';

use RockAdmin\Config\Reference;
use RockAdmin\Config\RootSchema;

$intro = <<<'TEXT'
Every key `rockadmin.php` accepts. A key that is not listed here does not
exist: the loader refuses an unknown key rather than ignoring it, and suggests
the nearest declared one when it looks like a typo.

Values may carry placeholders. `{{env.NAME}}` and `{{config.path.to.key}}`
resolve once, while the configuration loads. `{{user.*}}` and
`{{workspace.*}}` survive as placeholders and bind per request, so they reach
the database as bound parameters and never as SQL text.
TEXT;

$markdown = Reference::markdown(RootSchema::create(), 'Configuration reference', $intro);

file_put_contents(__DIR__ . '/../docs/reference/configuration.md', $markdown);

echo 'Wrote docs/reference/configuration.md (' . strlen($markdown) . " bytes).\n";
