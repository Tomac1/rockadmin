<?php

declare(strict_types=1);

/**
 * Which reference files exist, and what goes in each.
 *
 * Required by both `bin/generate-reference.php`, which writes them, and
 * ReferenceIsCurrentTest, which fails the build when a committed file and its
 * schema disagree. One list, read twice — a second copy would let the
 * generator and the check drift, and the check would still pass.
 *
 * @return list<array{file: string, schema: \RockAdmin\Config\Schema, title: string, intro: string}>
 */

use RockAdmin\Config\RootSchema;
use RockAdmin\Page\PageSchema;

$configurationIntro = <<<'TEXT'
Every key `rockadmin.php` accepts. A key that is not listed here does not
exist: the loader refuses an unknown key rather than ignoring it, and suggests
the nearest declared one when it looks like a typo.

Values may carry placeholders. `{{env.NAME}}` and `{{config.path.to.key}}`
resolve once, while the configuration loads. `{{user.*}}` and
`{{workspace.*}}` survive as placeholders and bind per request, so they reach
the database as bound parameters and never as SQL text.

The pages themselves are described in their own files and documented in
[the page reference](pages.md).
TEXT;

$pagesIntro = <<<'TEXT'
Every key a page file accepts. One file per page, under the directory
`pages_path` names, and the file's own name is the page's: `pages/ads.php` is
the page `ads`, reachable at `/p/ads`.

A page file is loaded the same way `rockadmin.php` is — shared definitions
expanded, placeholders resolved, validated against this schema, defaults
applied — so `['use' => '@column:id']`, `{{env.*}}` and `@enum:` references
all work here too, and an unknown key is refused rather than ignored.

Two things are worth reading before writing one. A **source** is a path, not
a column name: `title` is a column of the entity's own table, `user.name`
crosses a declared relation, and `stats->daily->views` traverses JSON inside
the row. And a column's **key is its identity** — it names the URL parameter
a filter uses, the CSS class the cell carries, and the template that can
override it. The label is decoration and can change freely; the key cannot.
TEXT;

return [
    [
        'file' => 'configuration.md',
        'schema' => RootSchema::create(),
        'title' => 'Configuration reference',
        'intro' => $configurationIntro,
    ],
    [
        'file' => 'pages.md',
        'schema' => PageSchema::create(),
        'title' => 'Page reference',
        'intro' => $pagesIntro,
    ],
];
