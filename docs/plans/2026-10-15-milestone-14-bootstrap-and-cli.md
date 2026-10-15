# Milestone 14 — The bootstrap and the command line

Everything in this repository works and almost nothing of it is installable.
`demo/index.php` is 801 lines of composition — loader, pages, connection,
identity, gate, roles, workspaces, throttle, mailer, storage, uploads, fifteen
handlers, assets, shell — and it is the *only* working example of how to start
RockAdmin. A project integrating today copies those 801 lines and owns them
forever: every handler this SDK adds afterwards is a line that project has to
be told to write.

Meanwhile `README.md` and specification 11.2 both promise

```php
RockAdmin::handle($path, basePath: base_path(), pdo: fn () => DB::connection()->getPdo());
```

and there is no class called `RockAdmin` anywhere in `src/`. We are documenting
an API we never wrote, which is worse than a missing feature: a missing feature
disappoints once, a documented missing feature disappoints after the decision
to adopt.

The command line is the same hole from the other side. `src/Console/` does not
exist, `composer.json` declares no `bin`, and the four SQL files in
`migrations/` are reachable only through `bin/ra-migrate.php`, a script that
takes a DSN on the command line because nothing else could hand it one.

This milestone closes both. Its acceptance test is a number: **`demo/index.php`
under 60 lines, with nothing lost.**

## Decisions this plan makes

**The composition root becomes a class, and the static facade is a wrapper over
it.** `RockAdmin::handle()` is the one-liner the documentation promises, and a
static method that builds a kernel out of superglobals is also the one thing
that cannot be tested, extended or run twice in a process. So the real object
is `RockAdmin\Admin` — built from a `Setup`, answering `handle(Request)` —
and `RockAdmin::handle()` is six lines on top of it: derive the path, boot,
send. Everything the tests and the CLI use is the object.

**`Setup` carries the services; configuration carries the data.** This is spec
11.2's rule and it decides the shape of the whole class: the PDO closure, the
session store, the mailer callback, the authenticator, the storage, the HTTP
client, the operations and any extra handlers are constructor arguments on
`Setup`, because none of them can be written into `config.cache.php`. Every one
of them has a default, so the smallest `Setup` is a base path.

**A page the host does not supply is one RockAdmin supplies.** Today the demo
registers an anonymous `dashboard` handler, and the brand link in the navbar
points at it — so a project that did not write one gets a 404 on the admin's
own home page. A minimal built-in dashboard ships here, as a handler like any
other, replaceable by registering your own against the same route. The richer
dashboard with `stat` regions stays in the built-in-pages milestone; this one
only owes the shell a page that exists.

**The menu moves into configuration.** It is a `list<MenuItemView>` built in
PHP today, which means the bootstrap cannot build it and every project writes
navigation in its composition root. `menu` becomes a `RootSchema` key — label,
page or route, children — and `Menu` keeps the permission filtering it already
does. A host that wants a computed menu still passes one through `Setup`.

**The database may come from configuration, and the closure wins.** Spec 11.2
keeps an explicit DSN for framework-less projects while handing the connection
to the bootstrap everywhere else. Both, then: `database.dsn`, `database.user`,
`database.password` are schema keys written with `{{env.*}}` placeholders, so
the committed file still carries nothing environment-specific — and a `pdo`
closure on `Setup` overrides them, because borrowing the host's connection is
the better answer whenever there is one to borrow.

**The CLI is argv and exit codes, and nothing else.** No command bus, no
container, no attributes. A `Command` is an interface with a name, a
description and `run(Input, Output): int`; `Application` matches a name and
prints the list when it does not. The commands that touch an environment build
their services through the same `Setup`/`Admin` the web entry point uses —
`doctor` reporting a connection the web request would not have opened is the
failure mode this exists to prevent.

**`init` writes a bootstrap file, and that file is the contract.** Every
environment command loads `config/rockadmin/bootstrap.php`, which returns a
`Setup`. That is how `migrate` and `user:create` behave identically under
Laravel and plain PHP without the CLI knowing either exists, and it is why
`init` comes after the bootstrap rather than before: it writes what the web
entry point already needs.

**`make:page` is last and may slip.** It is the only task here that is a
convenience rather than a gap — a page config can be written by hand in ten
minutes, and nothing is blocked by its absence. If the milestone runs long,
it goes to its own interlude rather than being hurried.

## Pre-flight scan

- `src/` has no top-level file and no `Console/`; the namespace root is free.
- `composer.json` has no `bin` key. Adding one is not a dependency.
- `bin/ra-migrate.php` already drives `Db\Migrator` over `migrations/*.sql`;
  the logic is done, the entry point is wrong.
- `Config\Cache` exists and nothing calls it — `cache:build` is wiring, not
  new code.
- `Menu` already filters by gate and identity, and `MenuItemView` already has
  children; only the configuration half is missing.
- `SetupHandler` already creates the first administrator over the web, so
  `user:create` is a second door onto solved logic.
- `Storages`, `Mailers`, `UploadLimits`, `Gate`, `Workspaces`, `Roles` all have
  `fromConfig()` already. The bootstrap is mostly calling them in order.

## Global constraints

1. No new dependency, as always — `ComposerConstraintsTest` is watching.
2. Every new configuration key lands in the schema with type, default,
   description and example, and `composer run docs:reference` is re-run.
3. The bootstrap is a composition root and may touch every layer; nothing else
   gains a reach it did not have.
4. Both databases run for every task that touches one.
5. The demo is rewritten on the bootstrap in the same task that makes the
   bootstrap able to carry it — never "the demo will be ported later".

## Tasks

### Task 1: `Setup` and `Admin`

The composition root as an object. `Setup` names the base path, the config
path, the URL mode and the host's services, each defaulted. `Admin::boot()`
performs, in order, what `demo/index.php` performs today, and `Admin::handle()`
answers a `Request`. Tests build an `Admin` over a fixture config and assert
that every route in `Router::ROUTES` has a handler — the check nobody could
write while the registry was assembled in a demo.

### Task 2: The menu, from configuration

`menu` in `RootSchema`; `Menu::fromConfig()` beside the existing filtering.
A `Setup` that is handed a menu uses it; otherwise the configured one.

### Task 3: A dashboard that exists

`DashboardHandler` in `src/Http/`, registered by the bootstrap, drawn through
the same shell every other page uses. The demo's anonymous class is deleted,
not moved.

### Task 4: The database, and the one-liner

`database.*` schema keys, `Connection` built from them when no closure is
given, and `src/RockAdmin.php`: path derivation for both URL modes (the block
at the bottom of `demo/index.php` today) plus `handle()`.

### Task 5: The demo, rewritten

`demo/index.php` becomes a `Setup` and a call. The two things it keeps are the
things a host really does supply: its `ArchiveAd` operation and its fixture
database. The no-database fallback keeps working, because cloning the
repository and looking at the theme is still a thing this demo is for.

### Task 6: The command line itself

`bin/rockadmin`, `Console\Application`, `Console\Command`, `Console\Input`,
`Console\Output`, `composer.json`'s `bin` key, and `rockadmin` with no
arguments printing the list. Colour only when the stream is a terminal.

### Task 7: `migrate`, `doctor`, `user:create`

The three a deploy needs. `migrate` absorbs `bin/ra-migrate.php` and keeps
`--dry-run`. `doctor` checks PHP version and extensions, the config files, the
writability of `paths.logs` and `paths.cache`, the connection, whether the
`ra_*` tables are current and whether an administrator exists. `user:create`
is interactive, with flags for the non-interactive case, and refuses to write
a role the configuration does not declare.

### Task 8: `init`

Writes `config/rockadmin/rockadmin.php`, `pages/`, `roles.php` and
`bootstrap.php`, each commented, and never overwrites what is there.

### Task 9: `cache:build`, `cache:clear`, `validate`, `schema`

Four small commands over `Config\Cache`, `Config\Validator` and `RootSchema`.
`validate` is the one CI runs.

### Task 10: `make:page`

Reads a table, infers column types, follows foreign keys, writes a commented
page. Optional, as above.

### Task 11: The installation guide, and the browser pass

`docs/guides/installation.md`, the README corrected where it describes what
now exists, the reference regenerated, and the demo opened in a browser —
signed in, a grid drawn, a row saved — because the last two milestones each
found a real bug that way and neither had a test that could.

## Milestone acceptance

- `demo/index.php` is under 60 lines and the demo behaves exactly as it does
  today, on both databases.
- A project integrates with a `composer require`, four CLI commands and one
  line in its router.
- Every route declared in `Router::ROUTES` has a handler in a default boot,
  asserted by a test.
- `composer run check` is green with both databases exported.
- The README describes only things that exist.

## What this milestone deliberately leaves out

- The richer dashboard, the profile page and the help page, with `nav` and
  `stat` regions — the built-in-pages milestone.
- The development console and `_diagnostics` — spec 12.
- The sweeps (`ra_login_attempts`, `ra_password_resets`, orphaned files). They
  are commands, so they belong to a CLI milestone, but they are not what stands
  between this SDK and a first integration.
- Packagist. The package name and the first tag are decisions, not work.
