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

Three things are worth reading before writing one. A **source** is a path,
not a column name: `title` is a column of the entity's own table, `user.name`
crosses a declared relation, and `stats->daily->views` traverses JSON inside
the row. A column's **key is its identity** — it names the URL parameter a
filter uses, the CSS class the cell carries, and the template that can
override it. The label is decoration and can change freely; the key cannot.
And `entity.key` must be among a region's own `columns` — a grid that never
selects it cannot attach a `collection`'s values back onto their row, cannot
keep the tiebreaker that stops two equally-sorted rows from swapping places
between pages, and cannot build a row's own detail URL.

A list region reads its state — search, filters, sort and page — out of its
own slice of the query string, namespaced under the region's key. For a
region keyed `grid`:

- `grid[q]` — the search term, matched against every column the region marks
  `searchable`.
- `grid[f][title]=bike` — a filter on the column `title`. A `select` or
  `multiselect` filter reads a list the same way: `grid[f][state][]=active`.
- `grid[f][price][from]=10&grid[f][price][to]=20` — a range filter, for a
  `range` or `date` column. Either end may be omitted.
- `grid[sort]=-created_at,id` — a comma-separated list of column keys, most
  significant first; a leading `-` sorts that column descending.
- `grid[page]=2` — the page number, one-based.

A parameter naming a column the region does not declare, or a shape its
filter does not expect, is dropped rather than raised — a stale or hand-
written link must still show a grid, not an error page.
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
