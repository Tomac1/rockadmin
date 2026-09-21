# RockAdmin

A dependency-free PHP administration SDK. Install it, mount it on a URL,
describe your pages in configuration files, and get a working admin.

> **Status: design stage.** The architecture is specified and agreed, but the
> implementation has not started. Nothing is installable yet. Watch the
> repository or read [the design specification][spec] if you want to follow
> along or shape it.

[spec]: docs/superpowers/specs/2026-09-21-rockadmin-design.md

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
Route::any('/admin/{path?}', fn ($path = '') => RockAdmin::handle($path, basePath: base_path()))
    ->where('path', '.*');
```

Without a framework, it is the same call from an `index.php`. Pretty URLs are
optional — `?ra=p/users` works just as well, so no mod_rewrite is required.

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
