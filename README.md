# RockAdmin

A dependency-free PHP administration SDK. Install it, mount it on a URL,
describe your pages in configuration files, and get a working admin.

> **Status: design stage.** The architecture is specified and agreed, but the
> implementation has not started. Nothing is installable yet. Watch the
> repository or read [the design specification][spec] if you want to follow
> along or shape it.

[spec]: docs/design/2026-09-21-rockadmin-design.md

## Why another admin package

Most admin packages are tied to one framework and one of its major versions.
When the framework moves, the admin has to move with it, and an admin panel
is exactly the kind of software that should be left alone for years.

RockAdmin takes the opposite approach. Its `composer.json` requires PHP and
three core extensions — nothing else:

```json
"require": {
    "php": "^8.4",
    "ext-pdo": "*",
    "ext-json": "*",
    "ext-mbstring": "*"
}
```

No Laravel, no Symfony, no ORM, no template engine, no npm, no build step.
There is nothing to conflict with whatever your project already uses, and
nothing that forces an upgrade when someone else ships a major version.

## How it works

Your project hands RockAdmin a request path; RockAdmin hands back a response.
In Laravel that is one line:

```php
Route::any('/admin/{path?}', fn ($path = '') => RockAdmin::handle(
    $path,
    basePath: base_path(),
    pdo: fn () => DB::connection()->getPdo(),
)->body)->where('path', '.*');
```

Without a framework, it is `RockAdmin::serve()` from an `index.php`, which
reads the path itself. Pretty URLs are optional — `?ra=p/users` works just as
well, so no mod_rewrite is required.

The installation is four commands, and
[the installation guide](docs/guides/installation.md) is the long version:

```bash
composer require tomac1/rockadmin
vendor/bin/rockadmin init          # writes config/rockadmin/*, commit it
vendor/bin/rockadmin migrate       # creates the ra_ tables
vendor/bin/rockadmin user:create   # the first administrator
vendor/bin/rockadmin doctor        # checks everything else
```

Everything an admin page does is described in one file kept in git:

```php
<?php return [
    'title'  => 'Ads',
    'layout' => 'list',

    'entity' => ['table' => 'ads', 'key' => 'id'],

    'regions' => [
        'grid' => [
            'type'     => 'list',
            'per_page' => 50,
            'sort'     => ['created_at' => 'desc'],
            'columns'  => [
                'id'         => ['type' => 'int', 'width' => '60px'],
                'title'      => ['type' => 'text', 'sortable' => true, 'link' => 'edit',
                                 'filter' => ['type' => 'text', 'op' => 'contains']],
                'user_name'  => ['type' => 'text', 'label' => 'Author',
                                 'source' => ['join' => 'users ON users.id = ads.user_id',
                                              'column' => 'users.name']],
                'price'      => ['type' => 'money', 'currency' => 'CZK', 'align' => 'right'],
                'created_at' => ['type' => 'datetime', 'format' => 'd.m.Y H:i'],
            ],
            'row_actions'  => ['edit', 'preview', 'copy', 'delete'],
            'bulk_actions' => ['delete', 'publish'],
        ],
    ],

    'form'    => ['fields' => [/* ... */]],
    'preview' => ['mode' => 'offcanvas', 'fields' => [/* ... */]],
];
```

Or let the scaffolder read your database and write the first draft:

```bash
vendor/bin/rockadmin make:page ads --table=ads
```

## Design principles

**Everything is a region.** A page is a layout with named slots, and each slot
holds a region with its own definition, its own templates and its own URL.
A list view is a page with one region. A message inbox is a page with a list
and a preview region that depends on it. Adding a new kind of page means
writing a layout file, not new code.

**Queries are explicit and inspectable.** Grids compose a single SELECT with
deduplicated JOINs. A one-to-many relation issues one extra query for the
whole page of rows, never one per row — there is no code path through which a
query can be made inside a row loop. The development console shows every query
with its timing, ready to copy and run against your database.

**Configuration is validated against a schema.** Every option has a schema
entry carrying its type, default, description and example. The reference
documentation is generated from it, and CI rejects any key the code reads but
the schema does not declare — so an undocumented feature cannot ship.

**Every element is addressable.** Alongside Bootstrap classes, each element
carries a structural class and an identity class:

```html
<td class="ra-grid-cell ra-grid-cell-price">
```

Restyle every grid in the application with one selector, or one cell on one
page, without touching a template. When CSS is not enough, copy the template
into your project — the cascade picks yours over the SDK's, file by file.

**Behaviour survives AJAX.** Listeners are delegated and per-element behaviour
is declared with attributes, so HTML inserted after page load works exactly
like HTML that arrived with it.

## Requirements

- PHP 8.4 or newer, with `pdo`, `json` and `mbstring`
- MySQL / MariaDB or PostgreSQL
- A way to route admin requests to one entry point — a rewrite rule, your
  framework's router, or `?ra=`

## Its own tables

RockAdmin keeps its administrators in its own `ra_`-prefixed tables rather
than in your `users` table: five admins and a hundred thousand customers need
different fields and different security properties, and your own schema stays
yours to restructure. The DDL ships with the package, in `migrations/`, so the
schema does not fork per project.

Run it once per deploy:

```bash
vendor/bin/rockadmin migrate            # --dry-run prints the SQL instead
```

or, where the application would rather call the class from its own deploy
hook:

```php
(new RockAdmin\Db\Migrator(RockAdmin\Db\Connection::fromPdo($pdo)))->migrate();
```

Either is safe to run every time: a migration already recorded in
`ra_migrations` is not applied again. One caveat is worth knowing before you
need it — PostgreSQL rolls a failed migration back completely, but MySQL
commits every `CREATE TABLE` implicitly, so a migration that fails halfway
there leaves what it had already created behind and has to be tidied by hand
before it is retried.

## Looking at it

A themed admin shell — navbar, menu, a page header with buttons, a flash
message — is running in this repository right now:

```bash
php -S localhost:8080 -t demo demo/index.php
```

Open <http://localhost:8080/>. See [`demo/README.md`](demo/README.md) for
what it is and is not: it is the design surface the default theme is tuned
against, kept honest to the general case.

`/p/ads` is a real grid over fixture tables — a filtered text column, a
joined column, money, an enum badge, JSON, a sortable date and a one-to-many
of tags, sorted and paginated with JavaScript on or off. It needs a
database; `demo/seed.php` fills one from the same variables the test suite
reads. See `demo/README.md` for the exact steps.

The same page also creates, edits, copies and deletes a row — a text field, a
textarea, a number, a select from a shared enumeration, a checkbox, a date and
a hidden field stamped with `@now`, all declared in the same file as the grid.
Every write is a plain `POST` with a CSRF token and runs inside a transaction,
so it works with JavaScript switched off, and an invalid submission comes back
with every error at once and what was typed still in the inputs.

All of it is behind a sign-in. `demo/seed.php` seeds three administrators — a
superadmin, an editor and a viewer, with Argon2id hashes — so the difference a
role makes is something you can look at rather than read about: the viewer's
grid has no actions column at all, the editor sees Edit and never Delete, and a
forbidden URL typed by hand is a 403 whether it addresses a page, a fragment or
a write. Every write leaves one row in `ra_activity_log` naming the person who
made it and carrying its diff.

## Documentation

- [Design specification][spec] — the complete architecture and the reasoning
  behind each decision
- Configuration reference and setup guides — published once implementation
  begins

## Contributing

Issues and pull requests are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md)
first; it explains the constraints this project is built around, the most
important being that RockAdmin does not take on dependencies.

Security vulnerabilities go through [private reporting](SECURITY.md), never a
public issue.

## Licence

MIT — see [LICENSE.md](LICENSE.md).
