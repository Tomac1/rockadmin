# Changelog

All notable changes to RockAdmin are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Every `post` action generates its own permission, `{page}.{verb}`:
  `@action:approve` on `formats` makes `formats.approve`. Roles grant it with
  `formats.*` or by name and subtract it with `!formats.approve`; `*.view`
  does not include it. A read-only page can now protect a write without
  borrowing `view` or needing a form region for `update`.
- The command line finds the bootstrap file through `RA_BOOTSTRAP` or
  `extra.rockadmin.bootstrap` in `composer.json` when `--bootstrap` is not
  given, and accepts options before the command name, so a Composer script
  `rockadmin --bootstrap=...` works with `composer admin -- migrate`.
- `init --dir=` writes the files to a directory of your choice.
- `doctor` warns when a Laravel project keeps the configuration under
  `config/`.

### Changed

- A `post` action with no `permission` now needs its generated
  `{page}.{verb}` instead of being allowed for every signed-in user. A role
  that should keep it needs `{page}.*`, `*`, or the permission by name.
- A `post` to `@action:view` is refused at load, because its permission
  would be the read permission every viewer holds.
- Under Laravel (an `artisan` file in the project), `init` writes
  `rockadmin/` instead of `config/rockadmin/`: Laravel loads everything under
  `config/` as its own configuration, which ran the bootstrap on every boot
  and broke `php artisan config:cache`. The bootstrap `init` writes now passes
  `configPath: __DIR__`.

## [0.1.0] - 2026-10-15

The first tagged version, and the first one a project can install. Everything
below was built between 2026-09-21 and today; it is listed as Added because
there was nothing before it.

What a 0.x says here: the configuration format, the page schema and the
public classes are in use and documented, and they may still change between
0.x releases where using them teaches us something. The `ra_` tables change
only through migrations, which are the one thing that cannot be re-run from
scratch.

### Added

- Design specification for v1: architecture, request lifecycle, configuration
  model, data layer, view layer, authentication, workspaces, installation and
  scope (`docs/design/2026-09-21-rockadmin-design.md`).
- Open-source project scaffolding: contribution guide, security policy, code
  of conduct, issue and pull request templates, CI across PHP 8.4 and 8.5 on
  MySQL and PostgreSQL, coding standards and static analysis configuration.
- `ComposerConstraintsTest`, which fails the build if RockAdmin gains a
  runtime dependency beyond PHP and its core extensions.
- HTTP foundation: request and response value objects, the route table and
  matcher, URL generation in path and query modes, the session abstraction,
  CSRF tokens, HTTP exceptions and the kernel.
- Configuration layer: schema with documentation and a default for every key,
  a validator that reports paths and suggests near misses, shared definitions
  with deep merge, placeholders that resolve at load or bind per request,
  shared enumerations, the directory loader with its local override, and a
  compiled cache.
- Data layer: MySQL and PostgreSQL dialects, a query builder composing one
  statement from declared relations and source paths, filters, search,
  ordering, offset and keyset paging, counting strategies, a row source whose
  one-to-many reads cost one query per page, and enumerations that read their
  options from a table.
- RockAdmin's own tables — `ra_users`, `ra_login_attempts`,
  `ra_password_resets`, `ra_activity_log` and `ra_migrations` — as one
  dialect-neutral migration file, and `Migrator`, which applies pending
  migrations in name order, records each with a checksum, refuses to continue
  when an applied migration has been edited since, and is safe to run on every
  deploy.
- The bootstrap: `Setup` for what a host supplies, `Admin::boot()` for the
  composition of every layer, `Services` for what the boot produced, and
  `RockAdmin::handle()` / `RockAdmin::serve()` as the line a project writes.
  The demo's 801-line composition root became 71 lines, and a project
  integrating RockAdmin no longer copies one.
- The command line, `vendor/bin/rockadmin`: `init`, `make:page`, `doctor`,
  `migrate`, `user:create`, `validate`, `schema`, `cache:build` and
  `cache:clear`. Every command loads the same `config/rockadmin/bootstrap.php`
  the web entry point does, so they operate on the installation a request
  would get. It replaces `bin/ra-migrate.php`, which is gone.
- `menu` as a configuration key, keyed by page — which is also the permission
  that decides who sees each item — and a built-in dashboard, so an
  installation answers its own front door without the host writing one.
- `database.dsn`, `database.user` and `database.password`, for a project with
  no connection to lend. A `pdo` closure on the bootstrap still wins.

### Changed

- Paths in the configuration resolve against the project root the bootstrap
  names, as specification 11.4 has always said and nothing implemented.
- `paths.cache` was removed. Where the compiled configuration lives cannot be
  written in the configuration, because it is read first; it is `cachePath`
  on `Setup`, and setting it makes the whole configuration one `include`.
- An unset `{{env.*}}` no longer fails validation on `public_url` or the
  `database.*` keys: those are the keys every project writes as placeholders,
  and not having set one yet is a state rather than an error.
- The "looks like a secret" warning no longer fires on the four keys that
  exist to hold secrets. A warning that fires on the correct arrangement is
  one people learn to ignore.
- An admin whose `storage.path` is empty now starts, and refuses the first
  upload with the same explanation, instead of refusing to boot at all.
- The audit log is told who is writing at the write rather than when the
  handlers were wired, so an installation that keeps one `Admin` cannot
  record every change under the first administrator to sign in.

[Unreleased]: https://github.com/tomac1/rockadmin/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/tomac1/rockadmin/releases/tag/v0.1.0
