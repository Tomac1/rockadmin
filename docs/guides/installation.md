# Installing RockAdmin

Four commands and one line in your router. This guide is the long version of
that: what each command does, what the files it writes are for, and what to
change for a project that already has a framework, a connection and a session.

## The short version

```bash
composer require tomac1/rockadmin
vendor/bin/rockadmin init          # writes config/rockadmin/*, commit it
vendor/bin/rockadmin migrate       # creates the ra_ tables
vendor/bin/rockadmin user:create   # the first administrator
vendor/bin/rockadmin doctor        # checks everything else
```

Then mount it. With a framework's router:

```php
Route::any('/admin/{path?}', fn ($path = '') => RockAdmin::handle(
    $path,
    basePath: base_path(),
    pdo: fn () => DB::connection()->getPdo(),
)->body)->where('path', '.*');
```

Without one, a `public/admin/index.php` of two lines:

```php
<?php

RockAdmin\RockAdmin::serve(require __DIR__ . '/../../config/rockadmin/bootstrap.php');
```

## What `init` writes

### `config/rockadmin/bootstrap.php` — the installation itself

This is the file that matters, and the reason the command line works the same
under a framework as without one: **both the web entry point and every
`rockadmin` command load it**. `doctor` therefore reports the connection a
request would open, rather than one assembled a second time from the same
keys by a different piece of code.

It returns a `Setup`, which carries everything configuration cannot hold:

| Argument | What it is for |
|---|---|
| `basePath` | The project root. Every relative path in the configuration resolves against it. |
| `baseUrl` | Where the admin is mounted, as a URL path: `/admin`. |
| `cachePath` | The compiled configuration. See [below](#the-compiled-configuration). |
| `pdo` | A closure returning the connection to borrow. |
| `session` | Your own session store, if you have one. |
| `authenticator` | Your own staff table, LDAP or SSO, in place of `ra_users`. |
| `storage` | Your own disk abstraction, in place of the configured driver. |
| `mailCallback` | What `mail.driver = 'callback'` means. |
| `operations` | Your write logic, by the name your pages use. |
| `handlers` | Routes you serve yourself — a dashboard of your own, for instance. |
| `menu` | A computed menu, where the configured one is not enough. |
| `clientIp` | The caller's address, if you sit behind a proxy you trust. |

Every one of them has a default, so the smallest setup is `new Setup(basePath:
__DIR__)`.

**Why these are arguments and not configuration keys:** a closure cannot be
written into a compiled PHP file, and the compiled file is what makes loading
the configuration a single `include` in production. Configuration holds data;
the host supplies services. That line is also what keeps the configuration
identical in every environment.

### `config/rockadmin/rockadmin.php` — the configuration

Committed, and it holds no secrets: anything that differs between your laptop
and production is written as `{{env.NAME}}`. Every key it may contain is
listed by `rockadmin schema`, or in
[the generated reference](../reference/configuration.md). A key that is not
listed does not exist — the loader refuses an unknown key rather than
ignoring it, and suggests the nearest declared one when it looks like a typo.

### `config/rockadmin/roles.php` — who may do what

Permission names are generated from your pages: a page `ads` with a form
region produces `ads.view`, `ads.create`, `ads.update` and `ads.delete`.
Naming a permission no page produces is refused when the admin starts, which
is how a typo stops being a silent loss of access.

### `config/rockadmin/pages/` — one file per page

Empty to begin with. `rockadmin make:page ads --table=ads` reads the table and
writes a working one, with the types guessed from the columns — right often
enough to start from and wrong often enough to read.

## Borrowing the connection

```php
pdo: fn () => DB::connection()->getPdo(),
```

One connection, one place holding the password, and transactions that do not
diverge when your own code writes alongside the admin. A project with nothing
to lend sets `database.dsn`, `database.user` and `database.password` instead,
as `{{env.*}}` placeholders; the closure wins where both exist.

Either way the admin keeps its administrators in its own `ra_`-prefixed
tables, never in your `users` table: five admins and a hundred thousand
customers need different fields and different security properties, and your
own schema stays yours to restructure.

## The URL

`url_mode` decides how links are built, and the two modes are a deployment
choice rather than a preference:

- **`path`** needs every request under the mount point to reach one entry
  point — a rewrite rule, or your framework's wildcard route. Links look like
  `/admin/p/ads`.
- **`query`** needs nothing at all, and works on hosting where you cannot
  configure anything. Links look like `/admin/index.php?ra=p/ads`.

`RockAdmin::serve()` reads the incoming path for whichever mode is configured.
A framework's router has already separated it, so `RockAdmin::handle()` takes
it as an argument.

## The compiled configuration

Set `cachePath` and the whole of `rockadmin.php`, the pages, the roles and the
enumerations become a single `include` sitting in opcache, instead of a
directory read and validated on every request.

```bash
vendor/bin/rockadmin cache:clear    # in the deploy, before the new code is live
vendor/bin/rockadmin cache:build    # after
```

It is written on the first request that finds it missing, so forgetting
`cache:build` makes one request slow rather than every request slow. The file
holds resolved `{{env.*}}` values — which is to say secrets — and absolute
paths for one machine, so it lives outside the document root and is never
committed.

The path is an argument rather than a configuration key for the reason the
key could never work: it says where the configuration is read from, and it is
needed before the configuration has been read.

## Every command

| Command | What it does |
|---|---|
| `init` | Writes the files above. Never overwrites one. |
| `make:page <name> --table=` | Scaffolds a page from a table. |
| `doctor` | PHP, extensions, configuration, writable paths, database, migrations, first administrator. |
| `migrate` | Creates or updates the `ra_` tables. `--dry-run` prints the SQL. |
| `user:create` | An administrator. Interactive, or `--email= --name= --role= --password=`. |
| `validate` | Loads and checks everything, changes nothing. The one for CI. |
| `schema [config\|pages\|roles]` | Prints the reference for that file. |
| `cache:build`, `cache:clear` | The compiled configuration. |

Every one of them takes `--bootstrap=path` when the bootstrap file is not at
`config/rockadmin/bootstrap.php`, and `--trace` to see where a failure came
from.

`migrate` is safe to run on every deploy: a migration already recorded in
`ra_migrations` is not applied again. It is **not transactional on MySQL and
cannot be** — MySQL commits implicitly on DDL — so a migration that fails
halfway there leaves what it already created and has to be tidied by hand
before a retry. PostgreSQL rolls back.

## Deploying

```bash
composer install --no-dev --optimize-autoloader
vendor/bin/rockadmin cache:clear
vendor/bin/rockadmin migrate
vendor/bin/rockadmin cache:build
vendor/bin/rockadmin doctor        # non-zero exit if anything is wrong
```

`doctor` returns a non-zero exit code when a check fails, so it is something a
deploy script can stop on.

## What is still manual

**Nothing sweeps the database or the storage.** `ra_login_attempts`,
`ra_password_resets` and files whose rows were deleted outside the admin all
grow without bound. There is no command for it in v1; a periodic `DELETE`
against the two tables is a cron job of two lines.

**There is no uninstall.** Dropping the `ra_` tables and deleting
`config/rockadmin` is all of it.
