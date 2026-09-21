# RockAdmin — working notes for agents

RockAdmin is a dependency-free PHP administration SDK. A project installs it,
mounts it on a URL, and describes its admin pages in configuration files kept
in git.

**Read [`docs/design/2026-09-21-rockadmin-design.md`](docs/design/2026-09-21-rockadmin-design.md)
before making any structural change.** It is the agreed design and records why
each decision was made. When code and specification disagree, say so rather
than silently following one of them.

Status: design agreed, implementation not started.

## Rules that are not negotiable

1. **No runtime dependencies.** `composer.json` requires `php`, `ext-pdo`,
   `ext-json`, `ext-mbstring` and nothing else. Never add a package to solve a
   problem — write the code, or expose an interface and put the integration in
   the documentation as a recipe. `ComposerConstraintsTest` enforces this.
2. **No framework coupling.** The core never mentions Laravel, Symfony or any
   other framework. Host integration goes through `SessionStore`,
   `Authenticator`, `Mailer`, `RowSource` and `WriteHandler`.
3. **MySQL and PostgreSQL both work, from the same configuration.** Database
   differences live in `Dialect` and never surface in configuration.
4. **No layer reaches two levels down.** Regions do not build SQL — they use a
   `RowSource`. Templates receive a prepared view object, never the database
   or the configuration.
5. **Every configuration key exists in the schema**, with type, default,
   description and example. The reference documentation is generated from the
   schema, so an undeclared key means an undocumented feature and fails CI.
6. **Template rules.** Every element carries its `ra-` structural and identity
   classes. No `<script>` tags — behaviour is declared with `data-ra-*`
   attributes and bound by delegation, so it survives AJAX insertion. No
   hardcoded URLs — use `UrlGenerator`, because the admin also runs in
   query-string mode. Escape everything with `$e()`; `$raw()` is the explicit
   exception.
7. **One write path.** Inline editing, forms and bulk actions all go through
   the same `WriteHandler`, transaction and audit log.
8. **No query inside a row loop.** Related columns are JOINs; one-to-many
   values are fetched once for the whole page of rows.

## Conventions

| Thing | Convention | Example |
|---|---|---|
| config keys | `snake_case` | `per_page` |
| database columns | `snake_case` | `created_at` |
| classes | `PascalCase` | `QueryBuilder` |
| methods, variables | `camelCase` | `buildQuery()` |
| CSS classes | `kebab-case`, `ra-` prefix | `ra-grid-cell-price` |
| data attributes | `kebab-case` | `data-ra-behavior` |
| placeholders | lowercase namespace, verbatim name | `{{env.MAIL_HOST}}` |

All code, comments, documentation and commit messages are in English. The
repository is public.

## Commands

```bash
composer run check     # coding standards, static analysis, tests
composer run cs:fix    # apply coding standards
composer run test      # PHPUnit
composer run stan      # PHPStan, level max
```

Database tests read `RA_TEST_MYSQL_*` and `RA_TEST_PGSQL_*` from the
environment and skip when those are unset. Local development uses MySQL on
localhost, database `rockadmin_test`, user `root`, password `root`.

## Layout

```
src/Http/     Request, Response, Kernel, Router, Csrf, SessionStore
src/Config/   Loader, Schema, Validator, Resolver, Cache
src/Db/       Connection, Dialect, QueryBuilder
src/Data/     RowSource, WriteHandler
src/Page/     Page, Layout, Region types
src/View/     Renderer, TemplateResolver, Escaper, FlashBag, Assets
src/Auth/     Authenticator, Gate, Roles, Workspaces, AuditLog
src/Mail/     Mailer + drivers
src/Console/  CLI commands
templates/    default templates, overridable by a project via a path cascade
assets/       vendored Bootstrap, core.js — no build step, no CDN
```
