# RockAdmin — Design Specification

**Date:** 2026-09-21
**Status:** Approved design, pre-implementation
**Scope:** v1 of the RockAdmin SDK

---

## 1. Purpose

RockAdmin is a dependency-free PHP administration SDK. A project installs it
via Composer, mounts it on a URL, and gets a working administration interface
driven entirely by configuration files kept in git.

Primary design goals, in priority order:

1. **Longevity.** The library must keep working for a decade without forced
   rewrites. This rules out coupling to framework major versions.
2. **Comprehensible architecture.** Every layer has one job and a named
   boundary. A developer can read one page config file and know everything
   about that page.
3. **Machine-writable configuration.** Most configuration will be written by
   AI agents. Every option must be discoverable from a machine-readable
   schema, and mistakes must produce precise, actionable errors.
4. **Predictable performance.** Query composition is explicit and inspectable.
   N+1 queries must be structurally impossible.
5. **Deep customisation.** Templates, CSS and HTML must be overridable without
   forking the library.

## 2. Non-goals

- Not a CMS, not a page builder, not a public-facing frontend.
- Not a replacement for the host application's own domain logic.
- No visual configuration editor in v1. Configuration is code in git.
- No build step. No npm, no Node, no asset compilation.

## 3. Hard constraints

| Constraint | Value |
|---|---|
| PHP | 8.4 minimum |
| Composer `require` | `php`, `ext-pdo`, `ext-json`, `ext-mbstring` — nothing else |
| Databases (v1) | MySQL / MariaDB, PostgreSQL |
| Frontend | Bootstrap 5 (vendored), `core.js` (vanilla), no build |
| Host frameworks | None required. Laravel, Symfony, plain PHP all supported by the same code path |

The Composer constraint is a rule, not a preference. Any addition requires an
explicit, documented decision.

**This does not mean RockAdmin cannot use libraries — it means it does not
depend on them.** Features that genuinely need one (a Google API client, a PDF
renderer, an S3 uploader) are built as an interface in the core plus an
implementation installed by the project:

```php
// The project installs dompdf, or Google's client, or whatever it prefers,
// and wires it in. RockAdmin's composer.json never mentions it.
'exporters' => ['pdf' => fn () => new MyPdfExporter()],
```

The project chooses the library and its version, upgrades it on its own
schedule, and a project that does not want the feature carries none of it.
`composer.json` may list such packages under `suggest`, which informs without
requiring. The same pattern already applies to `Mailer`, `SessionStore`,
`Authenticator`, `RowSource` and `WriteHandler` — it is the project's standard
answer to "we need library X", not an exception made for one case.

## 4. Architecture

### 4.1 Layers

```
Host project (Laravel / plain PHP / anything)
        | passes a Request
        v
   Kernel --> Router --> PageController
                              |
                    +---------+---------+
                    v                   v
              Config layer         Region (list/form/preview/nav/stat)
              (arrays + schema           |
               + validator)       +------+------+
                                  v             v
                             RowSource     WriteHandler
                             (reads)       (writes)
                                  |             |
                                  +------+------+
                                         v
                                   Db: Connection + Dialect
                                   (MySQL / PostgreSQL)
                                         v
                                     Renderer
                             (template cascade -> HTML)
```

In MVC terms: the **model** is a config-described entity plus its `RowSource`
and `WriteHandler`; the **view** is the PHP template tree; the **controller**
is `PageController`, which assembles regions and renders them.

### 4.2 Package structure

```
rockadmin/
├── src/
│   ├── Http/       Request, Response, Kernel, Router, Redirect, Csrf, SessionStore
│   ├── Config/     Loader, Schema, Validator, Resolver, Cache
│   ├── Db/         Connection, Dialect (MySqlDialect, PgDialect), QueryBuilder
│   ├── Data/       RowSource, WriteHandler, SqlRowSource, SqlWriteHandler
│   ├── Page/       Page, Layout, Region + region types
│   ├── View/       Renderer, TemplateResolver, Escaper, FlashBag, Assets
│   ├── Auth/       Authenticator, Users, Gate, Roles, Workspaces, AuditLog
│   ├── Mail/       Mailer interface + Log/Smtp/Sendmail/Callback drivers
│   └── Console/    init, doctor, migrate, user:create, make:page, cache:*, validate, schema
├── templates/      default templates (overridable via cascade)
├── assets/         bootstrap.css, bootstrap.bundle.js, core.js, rockadmin.css
└── docs/           generated reference + hand-written guides (English)
```

### 4.3 Architectural rules

These are enforced in review and, where possible, in CI:

1. **No layer reaches two levels down.** A region never builds SQL directly;
   it goes through a `RowSource`. A template never touches the database or the
   configuration; it receives a prepared view object.
2. **Composer dependencies stay at four extensions.**
3. **Templates contain no `<script>` tags.** Behaviour is declared with
   attributes (see 8.6).
4. **Templates never hardcode URLs.** All links go through `UrlGenerator`.
5. **Every config key read by code exists in the schema.** Enforced by a CI
   test; an undeclared key is rejected by the validator, so an undocumented
   feature cannot ship.

### 4.4 Deliberate omissions

- **No PSR-7.** PSR-7 is interfaces only; a working implementation would be a
  dependency. RockAdmin ships its own small `Request`/`Response`. A PSR-7 and
  a Laravel bridge are documented recipes, roughly five lines each.
- **No DI container.** Services are constructed in one place (`RockAdmin`),
  where the host can swap any of them.

## 5. Request lifecycle and routing

### 5.1 Routes

URLs use fixed prefixes by kind. Routing is unambiguous and stable.

| Path (after the mount prefix) | Purpose |
|---|---|
| `/` | dashboard |
| `/login`, `/logout` | authentication |
| `/password/reset`, `/password/reset/{token}` | password reset |
| `/p/{page}` | page (list view, settings, anything) |
| `/p/{page}/create` | empty form for a new row |
| `/p/{page}/{id}` | detail as a standalone page |
| `/p/{page}/{id}/edit` | form for an existing row |
| `/p/{page}/{id}/copy` | form prefilled from a row, saving as a new one |
| `/r/{page}/{region}` | **HTML fragment of one region** (AJAX) |
| `/a/{page}/{action}` | POST only — create, update, delete, custom |
| `/w/{workspace}` | POST only — switch workspace |
| `/_assets/{file}` | CSS, JS, icons from the SDK |
| `/_diagnostics` | environment check (requires `dev.console`) |
| `/_setup` | first-run setup, token-gated (see 11.4) |

**`/p/` renders, `/a/` changes data.** There is deliberately no
`/p/{page}/{id}/delete`: every GET request must be safe, because link
scanners, browser prefetching, mail security proxies and antivirus software
all follow links without a user ever clicking them. Deletion is a POST to
`/a/{page}/delete` carrying a CSRF token.

Copy and create therefore have `/p/` routes — they only render a form —
while saving either goes to `/a/{page}/create`.

Switching the workspace is a POST for the same reason. It changes what the
user sees for the rest of the session, and browsers prefetch links on hover;
a GET route would let a hover over the workspace menu quietly change the
active workspace. The switcher submits instead of linking.

### 5.2 Lifecycle

```
1. Host           -> Request (method, path without prefix, query, body, cookies)
2. Bootstrap      -> load configuration (single cache file in production)
3. Session        -> resolve identity
4. Auth gate      -> not logged in? redirect to /login (except /login, /_assets)
5. Workspace      -> from session, validated against permissions, fills {{workspace.*}}
6. Router         -> select handler
7. Permission     -> may this user access this page and this action?
8. Controller     -> assemble regions; for /r/ only the requested one
9. Region         -> RowSource or WriteHandler -> Db
10. Render        -> layout + regions + flash -> Response
```

**Full pages and fragments share every step.** `/r/{page}/{region}` runs the
same pipeline as `/p/{page}`, rendering one region instead of a layout. There
is no second code path, so the permission check in step 7 cannot be forgotten
for AJAX endpoints — historically the most common security hole in admin tools.

### 5.3 URL modes

The kernel never sees a URL, only a path string. The host decides how the path
is obtained:

```php
RockAdmin::handle('p/users', basePath: base_path());   // rewrite / framework router
RockAdmin::handle($_GET['ra'] ?? '', ...);             // /admin/index.php?ra=p/users
RockAdmin::handle($_SERVER['PATH_INFO'] ?? '', ...);   // /admin/index.php/p/users
```

Only link generation differs, controlled by one config key:

```php
'url_mode' => 'path',   // default: /admin/p/users?page=2
'url_mode' => 'query',  //          /admin/index.php?ra=p/users&page=2
```

`path` mode needs every admin URL to reach one entry point. Under a framework
router that is already true. Otherwise, Apache:

```apache
# public/admin/.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php [QSA,L]
```

or nginx:

```nginx
location /admin/ {
    try_files $uri /admin/index.php$is_args$args;
}
```

Either way `index.php` passes `$_SERVER['PATH_INFO']` or its own parsed path
to `RockAdmin::handle()`. Where neither is possible, `query` mode needs no
server configuration at all.

A CI test fails the build on hardcoded `/p/` in `href` attributes inside
templates.

### 5.4 Server requirements

- PHP 8.4+, `ext-pdo` with a MySQL or PostgreSQL driver, `ext-json`, `ext-mbstring`
- Ability to route all admin requests to a single entry point (rewrite,
  framework router, or `?ra=`)
- Writable cache directory (optional; without it the admin runs slower but runs)

No `.htaccess`, no mod_rewrite, no Node required. Sample Apache and nginx
configs ship in the docs.

### 5.5 Sessions and CSRF

The kernel knows a `SessionStore` interface (`get`, `set`, `forget`,
`regenerate`). The default implementation uses native PHP sessions; a Laravel
bridge is a documented five-liner. Calling `session_start()` directly would
break hosts that manage their own sessions, so the core never does.

CSRF tokens are required on every POST, stored in the session, and sent
automatically by `core.js` in a header.

### 5.6 Errors

Two modes. In development: exactly what is wrong, which file, which line, what
was expected. In production: logged, with a neutral page for the user.

## 6. Configuration model

### 6.1 Layout

```
config/rockadmin/
├── rockadmin.php        db connection, url_mode, paths, template paths, cache
├── rockadmin.local.php  gitignored, overrides selected keys locally
├── menu.php             menu structure
├── workspaces.php       workspaces and their variables
├── roles.php            roles and permissions
├── enums.php            shared enumerations
└── pages/
    ├── ads.php          one page = one file
    └── users.php
```

One page is one file, and it contains everything about that page. No jumping
around the repository.

A page is a layout holding **regions**: a region is one independently
renderable part of a page — a grid, a form, a detail panel, a side menu —
with its own definition, its own templates and its own URL, so it can be
re-rendered on its own without reloading the page. Most pages have one
region; a two-pane inbox has two. Regions are specified in 8.4.

### 6.2 Example page

```php
<?php return [
    'title'  => 'Ads',
    'layout' => 'list',

    'entity' => [
        'table'     => 'ads',
        'key'       => 'id',
        'scope'     => ['site_id' => '{{workspace.site_id}}'],
        'relations' => [
            'user' => ['table' => 'users', 'on' => 'users.id = ads.user_id'],
        ],
    ],

    'header' => [
        'description' => 'Manage ads across categories.',
        'buttons'     => [
            'create' => ['label' => 'New ad', 'action' => 'create', 'icon' => 'plus'],
        ],
    ],

    'regions' => [
        'grid' => [
            'type'     => 'list',
            'per_page' => 50,
            'sort'     => ['created_at' => 'desc'],
            'columns'  => [
                'id'         => ['use' => '@column:id'],
                'title'      => ['type' => 'text', 'sortable' => true, 'link' => 'edit',
                                 'filter' => ['type' => 'text', 'op' => 'contains']],
                'user_name'  => ['use' => '@column:name', 'label' => 'Author',
                                 'source' => 'user.name'],
                'price'      => ['type' => 'money', 'currency' => 'CZK', 'align' => 'right'],
                'state'      => ['type' => 'enum', 'options' => '@enum:ad_state',
                                 'display' => 'badge', 'filter' => ['type' => 'select']],
                'is_active'  => ['type' => 'bool', 'display' => 'check'],
                'progress'   => ['type' => 'int', 'display' => 'progress', 'max' => 100],
                'views'      => ['type' => 'int', 'source' => 'stats->daily_views'],
                'created_at' => ['use' => '@column:created_at'],
                'tools'      => ['type' => 'actions', 'items' => ['edit', 'preview', 'copy', 'delete']],
            ],
            'bulk_actions' => ['delete', 'publish'],
        ],
    ],

    'form'    => ['fields' => [/* ... */]],

    // No 'fields' key: the preview inherits the grid's columns.
    'preview' => [],
];
```

### 6.3 Principles

**The key is identity, the label is decoration.** `price` propagates into the
sort URL parameter, into permissions, into the CSS class `ra-grid-cell-price`,
and into the template path `region/list/cell/price.php`. Labels change freely;
keys are a contract.

**Text that may contain markup says so.** A plain string is escaped:

```php
'description' => 'Manage ads across categories.',
'description' => ['html' => 'See the <a href="...">import guide</a> first.'],
```

The second form is the same explicit opt-in as `$raw()` in templates: markup
is possible where it is genuinely useful, and it is greppable and visible in
review rather than being the silent default. The page header is also where
widgets will eventually go — counters, filters that apply across regions,
status strips — so `header` is specified as an open structure rather than a
fixed pair of keys.

**Convention over configuration.** Anything omitted is derived. Missing
`label` comes from the key (`created_at` -> "Created At"). Missing `type`
comes from the database column type. Missing `source` means the column is
named like the key. The minimal column is `'title' => []`.

**Data type and presentation are two different things.** `type` says what the
value *is* — how it is read from the database, cast, sorted, filtered and
validated. `display` says how it is *drawn*. One boolean column can be a tick
or a cross, a "Yes"/"No", or a live switch; one integer can be a number, a
percentage or a progress bar. The data underneath is identical, and filtering
and sorting must not change with the presentation.

```php
'is_active' => ['type' => 'bool', 'display' => 'check'],     // tick / cross
'is_active' => ['type' => 'bool', 'display' => 'yesno'],     // "Yes" / "No"
'is_active' => ['type' => 'bool', 'display' => 'switch'],    // live toggle (see 8.9)
'progress'  => ['type' => 'int',  'display' => 'progress', 'max' => 100],
'progress'  => ['type' => 'int',  'display' => 'percent'],
```

Both are registries, not switches. A **type** is a class that reads and casts
a value and declares which filter operators and which form fields suit it.
A **display** is a template, `region/list/cell/{display}.php`. Omitting
`display` picks the type's default one, which is why `'title' => []` still
renders correctly.

Adding a presentation therefore costs one template and nothing else — no
class, no type, no change to querying. Restyling an existing one means copying
its template into the project. This separation is what keeps the template
cascade useful: a project can change how every boolean in the admin looks
without touching a single page configuration.

**Placeholders resolve at load time**, always into bound parameters, never
into SQL text:

| Placeholder | Source |
|---|---|
| `{{workspace.*}}` | active workspace variables |
| `{{user.*}}` | logged-in admin user |
| `{{env.*}}` | environment, via an `EnvReader` the host may replace |
| `{{config.*}}` | other configuration values |

`EnvReader` defaults to `getenv()`; Laravel projects substitute one line,
because `$_ENV` is often empty once the framework caches its config. In
development the validator warns when a placeholder name contains `KEY`,
`SECRET`, `PASSWORD` or `TOKEN`, since those values may end up rendered.

**The schema is law.** Every key read anywhere in the code has a schema entry
with type, default, description, example and, where relevant, a performance
note:

```php
'sortable' => [
    'type'        => 'bool',
    'default'     => false,
    'description' => 'Allows sorting by this column. Requires a real DB column or an indexable expression.',
    'example'     => true,
    'performance' => 'Sorting by a non-indexed column causes a filesort on large tables.',
],
```

Three artefacts are generated from it:

1. **Validator** — runs on load in development and as `rockadmin validate`.
   Unknown key -> error with the nearest valid suggestion. Wrong type ->
   error. Missing required key -> error.
2. **Reference documentation** — `docs/reference/*.md`, generated, never
   hand-written, therefore never stale.
3. **Machine-readable dump** — `rockadmin schema --json`, the complete option
   set in one command, for agents.

A CI test compares keys read in code against the schema and fails the build on
a mismatch.

### 6.4 Shared definitions

Repeating the same column definition on nine pages is how configuration rots:
the tenth page gets it slightly wrong, and nobody notices. Definitions are
therefore written once and referenced.

```php
// config/rockadmin/defs.php
<?php return [
    'column' => [
        'id'         => ['type' => 'int', 'width' => '60px', 'sortable' => true,
                         'align' => 'right'],
        'created_at' => ['type' => 'datetime', 'format' => 'd.m.Y H:i',
                         'sortable' => true, 'filter' => ['type' => 'date_range']],
        'name'       => ['type' => 'text', 'sortable' => true,
                         'filter' => ['type' => 'text', 'op' => 'contains']],
    ],
    'field' => [
        'created_at' => ['type' => 'datetime', 'readonly' => true],
        'email'      => ['type' => 'text', 'validate' => ['email', 'max:255']],
    ],
    'preview' => [
        'timestamps' => ['created_at' => '@field:created_at', 'updated_at' => '@field:created_at'],
    ],
];
```

A reference is always written as `['use' => '@namespace:key']`, even when
nothing is overridden:

```php
'id'         => ['use' => '@column:id'],
'created_at' => ['use' => '@column:created_at', 'label' => 'Published'],
'note'       => ['use' => '@column:name', 'filter' => null],  // null removes an inherited key
```

A shorter bare-string form was considered and rejected. A node that is
sometimes a string and sometimes an array forces every reader — the
validator, the reference generator, the scaffolder, a person skimming a diff
— to handle two shapes, and adding the first override later rewrites the line
instead of adding to it. One shape costs four characters and removes a class
of mistakes.

This is why `'options' => '@enum:ad_state'` stays a bare string and does not
contradict the rule: there, the reference is the *value of a key*, like any
other scalar. Here, the reference *is the node* being defined. Different
positions, different rules, and the `@namespace:key` syntax is the same in
both.

Rules:

- **Merging is deep.** `['use' => '@column:created_at', 'filter' => ['op' => 'after']]`
  keeps the inherited filter type and replaces only the operator.
- **`null` removes** an inherited key, so a shared definition can be pruned
  rather than copied.
- **Definitions may reference definitions.** Resolution happens once at config
  load and is baked into the production cache, so there is no runtime cost.
  The validator detects reference cycles and reports the chain.
- **Namespaces match where the definition is used**: `column`, `field`,
  `filter`, `preview`, `action`, `enum`. A `@field:` reference inside
  `columns` is a validation error, because the two have different keys.
- **A reference to a missing definition is an error**, listing the nearest
  existing key. It never silently resolves to an empty definition.

`make:page` emits references where a definition already covers a column, which
keeps generated configuration consistent with the hand-written kind.

### 6.5 Enumerations

```php
<?php return [
    'ad_state' => [
        'active' => ['label' => 'Active',  'color' => 'success'],
        'draft'  => ['label' => 'Draft',   'color' => 'secondary'],
    ],
    'categories' => [
        'source' => ['table' => 'categories', 'value' => 'id', 'label' => 'name',
                     'order' => 'name', 'cache' => 300],
    ],
];
```

Static or database-backed with caching, so a grid of fifty rows never triggers
fifty lookups. Referenced as `@enum:ad_state` from filters, form selects and
badge colouring — defined once, used anywhere.

### 6.6 Caching

In production all configuration files merge into a single `config.cache.php`
— one `include`, opcache-friendly. `rockadmin cache:build` writes it,
`cache:clear` removes it. If the file is missing and the directory is
writable, it is built on the first request, so deployment without CLI still
works. In development files are read and validated on every request.

### 6.7 Naming conventions

| Thing | Convention | Example |
|---|---|---|
| config keys | `snake_case` | `per_page`, `remember_state` |
| database columns | `snake_case` | `created_at` |
| classes | `PascalCase` | `QueryBuilder` |
| methods, variables | `camelCase` | `$rowCount`, `buildQuery()` |
| CSS classes | `kebab-case`, `ra-` prefix | `ra-grid-cell-price` |
| data attributes | `kebab-case` | `data-ra-behavior` |
| placeholders | lowercase namespace, verbatim name | `{{env.MAIL_HOST}}` |

## 7. Data layer

### 7.1 Query composition

```
SELECT    <- columns from 'columns' only; never SELECT *
FROM      <- entity.table
JOIN      <- from the relations that columns reference, deduplicated
WHERE     <- entity.scope (workspace) AND filters AND search
             (row-level permissions attach here in a later version)
ORDER BY  <- 'sort' from the URL, validated against the column list
LIMIT     <- per_page + offset, or keyset
```

### 7.2 Where a column's value comes from

Writing a JOIN on every column that needs one is verbose and invites the same
join to be written three slightly different ways. Relations are declared once
on the entity, and columns address them by path:

```php
'entity' => [
    'table'     => 'ads',
    'relations' => [
        'user'     => ['table' => 'users', 'on' => 'users.id = ads.user_id'],
        'category' => ['table' => 'categories', 'on' => 'categories.id = ads.category_id',
                       'type' => 'inner'],
    ],
],

'columns' => [
    'author'  => ['source' => 'user.name'],            // JOIN users, select users.name
    'company' => ['source' => 'user.company.name'],    // chained relations
    'views'   => ['source' => 'stats->daily_views'],   // JSON inside this table
    'title'   => [],                                   // ads.title, by convention
],
```

Four forms, in order of how often they are used:

| Form | Meaning |
|---|---|
| omitted | a column of the entity's own table, named like the config key |
| `'column_name'` | a column of the entity's own table, named differently |
| `'relation.column'` | traverses a declared relation; dots chain further |
| `'column->json->path'` | JSON traversal, translated per dialect |

Each distinct relation used on a page produces exactly one JOIN, whether one
column references it or six. Unused relations produce none, so declaring them
generously costs nothing.

Relations are **declared, not inferred**. Guessing a join from a foreign key
is convenient right up to the schema that has two foreign keys to the same
table, where the guess silently picks the wrong one. `make:page` does read
foreign keys and writes the `relations` block for you — so the convenience is
there, but it lands in configuration you can read and correct, not in runtime
magic.

`->` is deliberately different from `.` because JSON traversal and relation
traversal are different operations: the first stays inside the row, the
second adds a JOIN. Using one character for both would make the cost of a
column invisible.

**When this is not enough** — a window function, a lateral join, an aggregate
over a filtered subset, or any join that needs hand-tuning — use the `query`
callback or a custom `RowSource` from 7.4. The path syntax is for the ninety
per cent that is ordinary; it is not meant to grow into a query language.
That growth is how configuration formats turn into bad ORMs.

### 7.3 Performance rules

**One query per page of data.** A column from another table is a JOIN, never a
per-row lookup. A 1:N relation needing multiple values (tags on an ad) issues
**one** supplementary query for all fifty ids at once. There is no code path
through which a query can be issued inside a row loop.

**Counting is a choice.** `'count' => 'exact' | 'cached' | 'estimate' | 'none'`.
`COUNT(*)` over ten million rows is the most expensive thing on the page.

The default stays `exact`, because a silently approximate total is a worse
default than a slow one: it is the kind of wrongness nobody notices until it
matters. Large tables are handled where the information exists instead —
`make:page` inspects the table size while scaffolding and writes
`'count' => 'cached'` with a comment when the table is large, and the
development console flags a counting query that takes too long. Keyset
pagination (`WHERE id < ? ORDER BY id DESC LIMIT 50`) is available for very
large tables and stays fast on page 100 000.

**Nothing from the request enters a query directly.** Sort column names are
matched against the configured column list and discarded if absent. Filter
values are bound parameters. Filter operators are an enum, not a string. The
grid is injection-safe by construction rather than by vigilance.

### 7.4 Extension points

1. `'query' => fn($q) => $q->where('deleted_at', null)` — adjust the built query
2. `'raw' => 'EXISTS (SELECT 1 FROM ...)'` — explicit raw SQL, documented as
   dangerous, never accepts request input
3. a custom `RowSource` — take over reading entirely (Eloquent, Elasticsearch,
   a REST API); the core only asks for "rows matching these filters, sort and
   page"

### 7.5 Dialects

`Dialect` centralises every difference between MySQL and PostgreSQL:
identifier quoting, `LIKE` vs `ILIKE`, boolean handling, JSON access,
`LIMIT/OFFSET`, full-text search. None of it may leak into configuration —
the same config must run on both. Tests run against both databases.

### 7.6 Default values for new rows

A form field may declare what a new row starts with. Existing rows are never
touched by it.

```php
'fields' => [
    'state'      => ['type' => 'enum', 'options' => '@enum:ad_state', 'default' => 'draft'],
    'site_id'    => ['type' => 'int', 'default' => '{{workspace.site_id}}', 'hidden' => true],
    'author_id'  => ['type' => 'int', 'default' => '{{user.id}}', 'hidden' => true],
    'published_at' => ['type' => 'datetime', 'default' => '@now'],
],
```

Defaults accept a literal, a placeholder (7.2 rules apply — they resolve to
bound values, never to SQL), or one of a small set of tokens such as `@now`
and `@uuid`. They are applied when the create form is rendered, so the user
sees what will be saved, and re-applied on save for hidden fields, so a
tampered form cannot drop them.

Copy is the same mechanism pointed the other way: `'copy' => ['reset' => ['state', 'published_at']]`
lists the fields that fall back to their defaults instead of being carried
over from the source row.

### 7.7 Writes

Writes run in a transaction through a `WriteHandler`. The default builds
INSERT/UPDATE/DELETE from the form definition. A project may replace it
wholesale (an `EloquentWriteHandler` is a ~40-line documented recipe, kept out
of the package so no ORM enters the dependency list) or override a single
method.

## 8. View layer

### 8.1 Template engine

Plain PHP files. Latte and Smarty are both good, but both are dependencies
with their own major versions and, in Latte's case, a compilation cache —
another writable directory to break on deploy. Plain PHP has zero
dependencies, zero compilation, maximum speed, and agents write it reliably.

Escaping is mandatory via `$e()`. Unescaped output requires `$raw()`, which is
visible in diffs and greppable. A CI test fails the build on `<?=` without one
of the two.

Templates receive a prepared view object and nothing else — no database, no
configuration. A template can therefore be overridden without understanding
the core, and a query cannot accidentally be smuggled into one.

### 8.2 Template cascade

```php
'template_paths' => [
    'resource_path/rockadmin',            // this project
    'vendor/company/admin-theme',         // shared house theme (optional)
    // the SDK default is always last, automatically
],
```

First match wins. A shared corporate theme can therefore live in its own
package without copying. Per-page overrides go through configuration:

```php
'templates' => ['row' => 'ads/row.php', 'cell/price' => 'ads/price.php'],
```

### 8.3 Template tree

```
templates/
├── layout/
│   ├── base.php            <html>, navbar, menu, shared modal + offcanvas, toasts
│   ├── single.php          one region (list view, dashboard)
│   ├── two-column.php      two regions side by side
│   └── sidebar-detail.php  list left, detail right (messages)
├── page/
│   ├── header.php          title, description, buttons
│   └── dev-console.php     footer debug panel
├── region/
│   ├── list/  region.php · toolbar.php · filters.php · table.php · head.php
│   │          body.php · row.php · cell/{type}.php · empty.php · pagination.php
│   ├── form/  region.php · field/{type}.php · errors.php
│   ├── preview/ · nav/ · stat/
├── mail/      reset-password.php · reset-password.txt.php
├── ui/        button.php · badge.php · icon.php · toast.php · modal.php
└── error/     403.php · 404.php · 500.php · config-error.php
```

### 8.4 Regions

**Everything is a region.** A page is a layout with named slots; each slot
holds a region with its own definition, its own templates and its own URL for
independent re-rendering. A list view is a page with one `list` region.
Settings is a page with a `nav` region and several `form` regions. A messages
inbox is a page with a `list` and a `preview` region. Adding a new page type
means writing a layout file, not new code.

Each region has:

- a **key** (`grid`, `detail`) — namespaces its URL parameters and its
  fragment address
- a **type** (`list`, `form`, `preview`, `nav`, `stat`) — selects the class
  and the template folder
- a **definition** — its slice of the page configuration
- its own URL `/r/{page}/{region}`
- **dependencies** on other regions

```php
'regions' => [
    'list'   => ['type' => 'list', 'select' => 'single'],
    'detail' => ['type' => 'preview', 'depends_on' => 'list.selected'],
],
```

The server renders `data-ra-refreshes="detail"` onto the list, and `core.js`
reloads only the dependent region on selection. No JavaScript is written by
the integrator; adding a third dependent region is one config line.

Layouts know nothing about regions — they fill named slots.

### 8.5 Actions and presentation

An earlier draft of this specification put presentation into the region:
`'preview' => ['mode' => 'offcanvas']`. That was wrong, and the mistake only
shows up once you want the same detail panel opened from two places, or a
grid cell that opens a panel belonging to a different page. A region cannot
know where it will be displayed, because that is not its property.

**A region renders content. An action decides where that content appears.**
The two are separate concerns and are configured separately.

An action is defined once and referenced wherever it should appear:

```php
'actions' => [
    // Navigate somewhere.
    'edit'    => ['type' => 'link', 'to' => '@page:ads/{id}/edit',
                  'icon' => 'pencil', 'permission' => 'ads.update'],

    // Load a region and show it in an overlay.
    'preview' => ['type' => 'open', 'in' => 'offcanvas', 'size' => 'lg',
                  'target' => '@region:preview', 'title' => 'Ad #{id}'],

    // The same region, shown as a modal instead.
    'quick'   => ['type' => 'open', 'in' => 'modal', 'target' => '@region:preview'],

    // Change data. Always POST, always confirmed, always permission-checked.
    'archive' => ['type' => 'post', 'to' => '@action:archive', 'icon' => 'archive',
                  'confirm' => 'Archive this ad?', 'permission' => 'ads.update',
                  'when' => ['state' => 'active'], 'refresh' => ['grid']],
],
```

Common keys: `type` (`link`, `open`, `post`), `label`, `icon`, `permission`,
`confirm`, `when` (row conditions deciding whether the action is offered),
and `refresh` (which regions re-render after it completes).

#### How pages, regions and actions are addressed

Every reference uses the same `@namespace:key` syntax as the rest of the
configuration. Three namespaces matter here:

| Reference | Points at | Resolves to the URL |
|---|---|---|
| `@page:users` | the page defined in `config/rockadmin/pages/users.php` | `/p/users` |
| `@page:users/{id}/edit` | that page's edit form for a row | `/p/users/42/edit` |
| `@region:detail` | the region `detail` **of the current page** | `/r/<current>/detail` |
| `@region:users.detail` | the region `detail` of the page `users` | `/r/users/detail` |
| `@action:archive` | the action handler on the current page | `/a/<current>/archive` |

So `@region:users.detail` reads as *page `users`, region `detail`*: the part
before the dot is the page file's name without `.php`, the part after it is a
key in that page's `regions` block. Spelled out, the two halves are:

```php
// config/rockadmin/pages/users.php
<?php return [
    'title'   => 'Users',
    'layout'  => 'list',
    'entity'  => ['table' => 'users', 'key' => 'id'],
    'regions' => [
        'grid'   => ['type' => 'list', 'columns' => [/* ... */]],
        'detail' => ['type' => 'preview', 'fields' => ['name', 'email', 'created_at']],
    ],
];
```

```php
// config/rockadmin/pages/ads.php — opens the users page's detail region
'actions' => [
    'author' => ['type' => 'open', 'in' => 'modal', 'size' => 'lg',
                 'target' => '@region:users.detail',
                 'params' => ['id' => '{user_id}']],
],
```

Clicking that action requests `/r/users/detail?id=123`, which renders the
`detail` region of the `users` page **under the `users` page's permissions**,
and `core.js` puts the returned HTML into a modal. No new mechanism: it is the
ordinary region fragment route from 5.1.

`target` accepts only `@region:` and `@page:` references, never a bare URL.
A raw URL cannot be permission-checked against a page, and would become the
obvious way to smuggle content into the admin frame.

#### Two kinds of placeholder

They look similar and mean different things, so the distinction is worth
stating plainly:

| Syntax | Resolved | Source |
|---|---|---|
| `{{user.id}}` | once, when configuration loads | the **logged-in admin**, workspace, env, config |
| `{user_id}` | per row, while rendering | a **column of the row** being rendered |

Writing `{{user.id}}` when you meant "the user this row belongs to" produces
the logged-in administrator's id on every row — a mistake that looks correct
in testing, because the developer is usually looking at their own records.
Row placeholders are URL-encoded on substitution and only keys present in the
region's data are accepted, so a row cannot inject a path.

#### Where actions appear

The same action may appear in several places at once, and a grid may carry
more than one action column:

```php
'header'  => ['buttons' => ['create', 'export']],       // page header
'columns' => [
    'activity' => ['type' => 'actions', 'items' => ['author']],   // early in the grid
    'title'    => ['type' => 'text'],
    'price'    => ['type' => 'money'],
    'tools'    => ['type' => 'actions', 'items' => ['edit', 'copy', 'delete']],
],
'bulk_actions' => ['delete', 'archive'],                // applied to selected rows
```

An action column is an ordinary column, so it is placed by its position among
the others and named like any other column. Name it for what it holds —
`activity`, `tools` — not `actions` and `actions2`: the key becomes the CSS
class `ra-grid-cell-tools` and the template override path, so a meaningful
name is worth more than a numbered one.

`items` accepts three forms, and they can be mixed:

```php
'items' => [
    'edit',                                                   // a named action, as defined
    ['use' => '@action:preview', 'in' => 'modal'],            // a named action, adjusted here
    'log' => ['type' => 'open', 'in' => 'modal', 'size' => 'xl',
              'target' => '@region:user_activity.log',
              'params' => ['user_id' => '{id}']],             // defined inline, used only here
],
```

Define an action in the page's `actions` block when it is used more than once
or belongs to the page's vocabulary; define it inline when it exists only in
that one column. Both produce the same thing.

**`preview` is then only shorthand.** A page that declares

```php
'preview' => ['fields' => [/* ... */]],
```

gets a region named `preview` and a row action named `preview` that opens it
in an offcanvas. Spelling both out explicitly produces exactly the same thing.
The shorthand exists because that arrangement is wanted on most pages; it is
not a separate mechanism, and nothing in the core treats "preview" specially.

**Which fields a preview shows:**

| Configuration | Meaning |
|---|---|
| key omitted | inherit the grid's columns |
| `'fields' => ['a', 'b']` | exactly these |
| `'fields' => '@all'` | every column of the table |
| `'fields' => []` | none — legal, but the validator warns, since it is almost always a mistake |

Inheriting by omission follows the same convention-over-configuration rule as
everything else, and an empty array keeps its plain meaning instead of being
overloaded into "all of them".

**Pages that exist only to be opened.** A page may be reachable by URL and
absent from the menu:

```php
'menu' => false,
```

Such a page is a normal page — same permissions, same lifecycle, same
security checks — it simply is not advertised. This is what makes
`'target' => '@region:users.detail'` sound: the panel loaded into a modal is
a region of a real page, not a special-cased fragment, and it is governed by
that page's permissions. Hiding a page from the menu is a convenience, never
a security measure.

### 8.6 Client-side JavaScript

Bootstrap 5 provides modal, offcanvas, dropdown, toast, tooltip and collapse
without jQuery, which removes the need for Alpine or Vue. Vue in particular is
rejected: it requires npm and a build step, its major versions churn, and it
would split the truth about the rendered HTML between PHP and JS, undermining
the goal that any element can be restyled or re-templated.

Three files, no build: `bootstrap.css`, `bootstrap.bundle.js`, `core.js`.

**Assets are vendored, not loaded from a CDN.** They are served from
`/_assets/` with a version hash and long cache headers. The Bootstrap version
is pinned to the RockAdmin version and cannot change underfoot; the admin
works offline and on intranets; no CSP exceptions; no third-party outage.

**Behaviour must survive HTML injected after page load.** This is solved at
the root rather than by preloading views:

1. **Delegation instead of binding.** No listener is attached to a button. All
   listeners sit on `document` and dispatch on attributes:

   ```html
   <button data-ra-action="preview" data-ra-target="#raModal" data-id="42">Detail</button>
   ```

   An element inserted an hour later behaves identically to one from the
   original page load. Bootstrap's own components work the same way via
   `data-bs-toggle`, so they also survive insertion.

2. **A behaviour registry for the rest.** Things that cannot be delegated
   (tooltips, date pickers, editors, charts) are declared and registered once:

   ```html
   <textarea data-ra-behavior="editor"></textarea>
   ```

   ```js
   RockAdmin.behavior('editor', {
       attach: el => el._ed = new SomeEditor(el),
       detach: el => el._ed.destroy(),
   });
   ```

   After **every** fragment insertion, `core.js` calls `attach(container)`,
   marking elements so nothing initialises twice. Before removal it calls
   `detach`, releasing instances and global listeners — the difference between
   a fresh and a degraded page in an admin tab left open all day.

3. **A fragment is always valid HTML** — never JSON, never containing a script
   tag. Modal metadata rides on the fragment root:

   ```html
   <div class="ra-region ra-region-preview ra-region-preview-ads"
        data-ra-title="Ad #42" data-ra-size="lg" data-ra-class="ra-modal-ads">
   ```

   `core.js` fills the single shared modal's title and classes from those
   attributes. Because the fragment is plain HTML, it can be opened directly
   in a browser for debugging.

4. **Per-view libraries load on demand**: `data-ra-requires="charts.js"` makes
   `core.js` fetch that asset once, wait, then run `attach`.

### 8.7 CSS conventions

Every element carries two or three layers of classes:

```html
<button class="btn btn-primary  ra-btn ra-btn-create  ra-btn-create-users">
<tr    class="                  ra-grid-row           ra-grid-row-users" data-id="42">
<td    class="                  ra-grid-cell          ra-grid-cell-price">
<body  class="                  ra-page               ra-page-users ra-page-type-list">
```

1. **Bootstrap classes** — appearance, replaceable
2. **Structural `ra-` class** — "this is a grid cell", identical everywhere;
   one selector restyles every grid in the application
3. **Identity `ra-` class** — "and this is the `price` cell on the `users`
   page"; restyle one thing without touching a template

`<body>` carries page identity, so `.ra-page-users .ra-grid-cell { }` works
even without identity classes. Configuration accepts `class` and `attrs` on
elements, so small adjustments need neither a template override nor CSS.

A template without `ra-` classes violates the standard.

### 8.8 Project assets

```php
'assets' => ['css' => ['/css/admin-theme.css'], 'js' => ['/js/admin-extra.js']],
```

Project CSS loads after the SDK's, so it overrides without `!important`.

### 8.9 Grid state and shareable URLs

**Grid state lives in the URL, never in the session.** Shareable links then
come for free.

```
/p/ads?grid[q]=bike&grid[f][state]=active&grid[sort]=-created_at&grid[page]=3
```

Parameters are namespaced per region, because a page may hold several regions.
On a filter change `core.js` re-renders that region and calls `pushState`, so
the back button works and the address bar always holds the shareable link.

**State carries into forms and back.** Edit links carry a return address:

```
/p/ads/42/edit?_ret=<encoded path including filters and page>
```

After saving, the user returns to exactly where they left — page three, same
filters, plus a toast. `_ret` is validated as an internal admin path; anything
with a scheme or host is discarded, otherwise this would be an open redirect.

Optionally `'remember_state' => true` stores the last state per page in the
session, restored when arriving from the menu with no parameters. **The URL
always wins**, so a shared link shows the sender's filters, not the
recipient's saved state.

**Deep links to detail use a query parameter, not a hash:**

```
/p/ads?preview=42    -> grid with the offcanvas already open
/p/ads/42            -> the same detail as a standalone page
```

A hash never reaches the server, so it would force a JS fetch after load —
slower, visibly flickering, and broken without JS. With a query parameter the
server renders both grid and populated offcanvas, and JS merely opens it.
`core.js` listens to `popstate`, so closing the overlay is a history step back.

### 8.10 Editable cells (reserved for v1.1)

Cells will gain in-place editing: click a pencil and the text is replaced by
an input, a select, or — for booleans — a checkbox that saves on change. This
is not in v1, but the v1 structures must not block it, so the shape is fixed
now:

- A column type is already a pair of "value class + display template". Editing
  adds a third part, an optional `region/list/cell/edit/{type}.php` template.
  A type without one is simply not editable.
- Configuration gains `'editable' => true` per column, plus the existing
  `'options' => '@enum:...'` for select and checkbox variants.
- Saving reuses the existing action route, `/a/{page}/inline-update`, with the
  same `WriteHandler`, the same transaction and the same audit log as a full
  form save. There must be no second write path.
- Permission granularity is `page.update`, with room for a later
  `page.update.{column}` when column-level permissions arrive.
- Behaviour is delegated (`data-ra-action="cell-edit"`), so editable cells
  work inside fragments re-rendered after a filter change without any
  initialisation.
- Validation is server-side and reuses the field definition from `form`. The
  response returns the re-rendered cell, so the grid always shows what was
  actually stored, not what was typed.

### 8.11 Flash messages

Flash messages live in the session, survive redirects, render once as
Bootstrap toasts and are then discarded. On a fragment request they travel
alongside the HTML fragment, so a toast appears after an AJAX action without a
reload.

## 9. Authentication, roles and workspaces

### 9.1 Users

Admin users live in their own tables, separate from the project's users. There
are five admins and a hundred thousand customers; they need different fields
and different security properties, and the project must be free to restructure
its own `users` table without breaking the admin. All tables use the `ra_`
prefix.

```
ra_users            id, email, password, name, is_active, roles, workspaces,
                    auth_provider, auth_subject, last_login_at, created_at
ra_login_attempts   email, ip, at
ra_password_resets  email, token_hash, expires_at, used_at
ra_activity_log     user_id, page, action, row_id, at, diff
ra_migrations       name, applied_at
```

`auth_provider` and `auth_subject` exist from day one so that adding OIDC in
v1.1 requires no migration.

The default `Authenticator` uses Argon2id password hashing, attempt
throttling, session regeneration on login, and logout everywhere. Projects
wanting to authenticate against their own table or against Laravel's auth
replace the three-method interface; a recipe ships in the docs.

### 9.2 Roles and permissions

Roles are configuration (git); assignment is data (database).

```php
<?php return [
    'superadmin' => ['permissions' => ['*']],
    'editor' => [
        'label' => 'Editor',
        'permissions' => ['ads.*', 'users.view', 'media.*', '!ads.delete'],
    ],
    'viewer' => ['permissions' => ['*.view']],
];
```

Permissions are named `page.action` and are generated automatically from the
page configuration, so a new page brings its own permissions. `*` widens, `!`
subtracts.

**Checks happen in one place.** `Gate::allows($user, 'ads.delete')` runs at
step 7 of the lifecycle, before anything executes, for full pages, fragments
and actions alike. The same gate is consulted during rendering, so menu items
and buttons hide themselves — never a visible button that returns 403.

### 9.3 Workspaces

A workspace is a named context: a bundle of variables, a set of visible pages,
and a unit of permission. It covers both configuration splitting and
multi-tenancy, and cases such as production/staging or per-country branches.

```php
<?php return [
    'cyklobazar' => [
        'label' => 'Cyklobazar.cz',
        'vars'  => ['domain' => 'cyklobazar.cz', 'site_id' => 1],
        'pages' => ['ads', 'users', 'categories'],
    ],
    'motobazar' => [
        'label' => 'Motobazar.cz',
        'vars'  => ['domain' => 'motobazar.cz', 'site_id' => 2],
        'pages' => ['ads', 'users'],
    ],
];
```

Admin users are not tenant-scoped; they are granted access to workspaces. The
active workspace lives in the session and is switched from the navbar, after
the brand.

Three checks must all pass:

1. does the user hold the page permission?
2. is the page listed in the active workspace?
3. is the row inside the workspace scope?

The third is applied to `WHERE` at query-build time, outside the reach of
filters, and applies to update, delete and preview as well as list. Were the
scope merely a default filter value, a user could edit it in the URL and read
another workspace's data.

The "all workspaces" option appears in the switcher only for users with access
to every workspace. When active, the scope is omitted and the grid
automatically adds a workspace column so rows remain attributable.

Workspace variables are values, not SQL. `{{workspace.site_id}}` becomes
`WHERE site_id = ?`. Genuine SQL fragments require the separate, explicitly
dangerous `raw_condition`, which may never accept request input.

### 9.4 Audit log

Every write is recorded with a value diff. It can be disabled, but defaults to
on. The write volume in an admin is a few rows per minute, so one extra INSERT
is not measurable, whereas history you did not collect cannot be recovered
later — and "who changed this" gets asked even in a one-person team, just six
months afterwards.

### 9.5 Deferred to v1.1+

OIDC (Google, Keycloak, Azure AD) — a redirect, a code-for-token exchange via
`ext-curl` and signature verification via `ext-openssl`, so still no Composer
dependency. Configuration shape is reserved now:

```php
'auth' => ['providers' => [
    'google' => ['type' => 'oidc', 'issuer' => 'https://accounts.google.com',
                 'client_id' => '{{env.GOOGLE_CLIENT_ID}}'],
]],
```

Also deferred: two-factor (TOTP), SSO beyond OIDC.

## 10. Mail and password reset

```php
'mail' => [
    'driver'     => 'log',              // log | smtp | sendmail | callback
                                        // the 'callback' driver's closure is
                                        // supplied at bootstrap, not here —
                                        // configuration holds data only
    'from'       => ['address' => '{{env.MAIL_FROM}}', 'name' => 'RockAdmin'],
    'host'       => '{{env.MAIL_HOST}}',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => '{{env.MAIL_USERNAME}}',
    'password'   => '{{env.MAIL_PASSWORD}}',
],
```

Four drivers, no dependencies:

- **`log`** (default) — one file per message,
  `logs/mail/2026-09-21_143012_reset-password_jan@example.com.eml`, complete
  with headers and openable in a mail client
- **`smtp`** — a socket SMTP client with AUTH and STARTTLS, roughly 200 lines,
  saving a dependency on PHPMailer and its release cycle
- **`sendmail`** — native `mail()`, for simple hosting
- **`callback`** — the project supplies a closure, routing mail through
  Laravel `Mail`, Symfony Mailer or anything else it already has

Mail templates live in `templates/mail/` and are overridable through the
cascade. Every message is sent in both HTML and plain text.

**Password reset** ships in v1: hashed single-use token, 60-minute validity,
throttling by email and IP, and a constant response ("if the address exists we
have sent a link") so the admin list cannot be enumerated.

## 11. Installation, environments and CLI

### 11.1 Two command categories

| Category | Commands | Where | Output |
|---|---|---|---|
| **Scaffolding** | `init`, `make:page` | locally, once | files committed to git |
| **Environment ops** | `migrate`, `user:create`, `doctor`, `cache:build`, `cache:clear`, `validate`, `schema` | in every environment | database and cache changes |

### 11.2 Installation

```bash
composer require rockadmin/rockadmin
vendor/bin/rockadmin init          # creates config/rockadmin/* with comments
vendor/bin/rockadmin doctor        # checks PHP, PDO, permissions, connection
vendor/bin/rockadmin migrate       # creates ra_* tables
vendor/bin/rockadmin user:create   # first admin, interactive
```

Plus one line in the project:

```php
Route::any('/admin/{path?}', fn ($path = '') => RockAdmin::handle($path, basePath: base_path()))
    ->where('path', '.*');
```

**Borrow the database connection rather than opening a second one:**

```php
RockAdmin::handle($path, basePath: base_path(), pdo: fn () => DB::connection()->getPdo());
```

One connection, one place holding the password, and transactions that do not
diverge when the project writes alongside the admin. An explicit DSN in
`rockadmin.php` remains available for framework-less projects.

The connection is handed to the bootstrap rather than written into the
configuration, and that is deliberate: **configuration holds data, the host
supplies services.** A closure cannot be written into `config.cache.php`, so a
configuration containing one could never be cached — and the cache is what
makes loading a single `include` in production. The same rule applies to every
service the host may replace: the mailer callback, the session store, the
environment reader and a custom `RowSource` are constructor arguments, not
configuration keys.

**Migrations belong to RockAdmin**, tracked in `ra_migrations`, idempotent,
with `--dry-run` printing SQL for hosts without CLI access. The project's own
migrations are neither touched nor read.

**The CLI runs without a framework.** `vendor/bin/rockadmin` loads
configuration and PDO through a small bootstrap file written by `init`, so
`migrate` and `user:create` behave the same under Laravel and plain PHP.

### 11.3 Scaffolding a page

```bash
vendor/bin/rockadmin make:page ads --table=ads
```

Reads the table schema, infers column types, follows foreign keys and
generates a working `config/rockadmin/pages/ads.php` with grid, form and
preview — commented, with alternatives noted at the important choices. Agents
then start from a working page rather than an empty file, which also keeps
generated configuration within convention.

### 11.4 Environment portability

Configuration is committed once and must be identical everywhere, so it
carries nothing environment-specific.

**Paths are relative to the project root** (`basePath`), supplied by the host:

```php
'paths' => [
    'logs'  => 'storage/logs/rockadmin',   // required
    'cache' => 'storage/cache/rockadmin',
],
```

Relative to the **project root, not the document root**. The document root is
`public/` under Laravel; logs placed relative to it would be web-readable, and
a reset-password `.eml` file contains a working reset link. `doctor` reports an
error when the log directory resolves inside the document root — easy to hit
on shared hosting where the document root is the project root. Absolute paths
remain legal for logs outside the project, but the validator warns that they
will diverge between environments.

**What differs goes into the environment** (`{{env.*}}`). **What differs
structurally** goes into a local override:

```
config/rockadmin/rockadmin.php        committed, shared
config/rockadmin/rockadmin.local.php  gitignored, overrides selected keys
```

So `dev_console => true` and `debug => true` exist locally and cannot
accidentally appear in production, because the file is not deployed. The
environment is read from `{{env.RA_ENV}}`, defaulting to `prod` — the safer
value should the variable be unset.

```bash
# locally, once
vendor/bin/rockadmin init
vendor/bin/rockadmin make:page ads --table=ads
git commit

# on the server, in the deploy script
composer install --no-dev
vendor/bin/rockadmin migrate
vendor/bin/rockadmin cache:build
```

**Shared hosting without CLI** has three escape routes:

1. `migrate --dry-run` locally prints plain SQL to paste into phpMyAdmin
2. **`/_setup`** can run migrations and create the first admin. It exists only
   when `RA_SETUP_TOKEN` is set in the environment and the token matches the
   URL. Without a valid token it returns 404 rather than 403, so it cannot be
   discovered. It locks itself after the first user is created.
3. **The cache builds itself** on the first request when the file is missing
   and the directory is writable; `cache:build` only accelerates deployment.

**`/_diagnostics`** exposes the `doctor` checks over the web to users holding
`dev.console`: PHP version, extensions, directory permissions, database
connectivity, cache state, configuration validity. On shared hosting this is
often the only way to see why something fails.

## 12. Dev console

Part of the layout, not a region: it collects data across the whole request
(queries from every region, timing, memory, which template resolved from which
path, how placeholders were substituted). It renders as a collapsed footer bar
expanding into a panel, `page/dev-console.php`.

Each query is shown twice: as sent (parameterised) and **interpolated for
direct execution**, ready to copy into a database client, with execution time,
row count and one-click `EXPLAIN`.

It requires two conditions simultaneously: `'dev_console' => true` in
configuration **and** the `dev.console` permission. The superadmin role alone
is not enough — if the configuration ever reached production by mistake, it
must not hand the database schema to the first person who gets in.

## 13. v1 scope

**In:**

- core: kernel, router, request/response, session, CSRF, error handling
- configuration: loading, schema, validator, placeholders, shared definitions,
  caching, local override
- database: PDO, MySQL and PostgreSQL dialects, query builder, declared
  relations with path sources and JSON traversal, offset and keyset pagination
- regions: `list`, `form`, `preview`, `nav`, `stat`; pages hidden from the menu
- layouts: `single`, `two-column`, `sidebar-detail`
- column types: text, int, money, datetime, bool, enum, image, link, relation
- displays: plain, badge, check, yesno, progress, percent, image, link
- form field types: text, textarea, number, select, multiselect, checkbox, radio, date, datetime, file, hidden, password
- filters: text, select, multiselect, range, date, boolean, plus multi-column search
- actions: `link`, `open` (modal, offcanvas, page), `post`; built-in create,
  update, copy, delete; row conditions, confirmation, region refresh; as
  header buttons, as a grid column, or as bulk actions
- form fields: default values for new rows, including placeholders and tokens
- auth: password login, roles and permissions from configuration, workspaces, password reset by email, audit log
- built-in pages: dashboard, profile, user management, help
- assets: Bootstrap 5 CSS + JS, `core.js`, default white theme
- dev console with SQL, validator, `doctor`, `/_diagnostics`, `make:page`
- documentation: generated reference plus hand-written guides, in English

**Out of v1, with space reserved:**

- OIDC (Google, Keycloak) — columns and config shape already present
- inline cell editing (see 8.9) — cell type registry and action route already
  shaped for it
- `chart` and `tree` regions
- row-level and column-level permissions — attach to `WHERE`, nothing rewritten
- SQLite and MSSQL — another dialect
- import/export, record versioning, two-factor, UI localisation
- a fluent builder facade over the arrays, should it prove necessary

## 14. Success criteria

A new project with an existing database goes from `composer require` to a
working page — list, filters, editing, deletion — **in under fifteen minutes,
writing no PHP outside configuration**.

Supporting criteria:

- `composer show --tree` lists no runtime dependency beyond PHP extensions
- the same configuration runs unchanged on MySQL and PostgreSQL, verified by
  the test suite on both
- a grid of 50 rows with 3 joined columns issues at most 3 queries in total
- every configuration key appears in the generated reference; CI fails otherwise
- no template contains a script tag, a hardcoded URL, or unescaped output; CI
  fails otherwise

## 15. Local development environment

MySQL on localhost, database `rockadmin_test`, user `root`, password `root`.
PostgreSQL parity is part of the test infrastructure.
