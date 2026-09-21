# Contributing to RockAdmin

Thank you for considering a contribution. This document explains how to work
on RockAdmin and, more importantly, the constraints it is built around. Those
constraints are not style preferences — they are the reason the project
exists, and a pull request that breaks one will be asked to change regardless
of how good the feature is.

Read the [design specification](docs/design/2026-09-21-rockadmin-design.md)
before proposing anything structural. It records what was decided and why.

## The constraints

**1. No runtime dependencies.** `composer.json` requires `php`, `ext-pdo`,
`ext-json` and `ext-mbstring`. Nothing else, ever, without an explicit
decision recorded in the specification. An admin panel should keep working for
a decade; every dependency is a future forced upgrade. `ComposerConstraintsTest`
enforces this, and it is meant to be hard to change.

If a feature seems to need a library, write the 200 lines instead, or expose
an interface and ship the integration as a documented recipe rather than as
code in the package.

**2. No framework coupling.** The core never references Laravel, Symfony or
any other framework. Host integration happens through small interfaces
(`SessionStore`, `Authenticator`, `Mailer`, `RowSource`, `WriteHandler`) whose
implementations for a given framework live in the documentation.

**3. Both databases, always.** The same configuration must produce correct
results on MySQL/MariaDB and PostgreSQL. Differences belong in `Dialect` and
must never surface in configuration. Any change touching SQL needs tests on
both.

**4. No layer reaches two levels down.** A region does not build SQL; it goes
through a `RowSource`. A template does not touch the database or the
configuration; it receives a prepared view object.

**5. Every configuration key is in the schema.** A key the code reads but the
schema does not declare fails CI. This is deliberate: it makes an
undocumented feature impossible to ship, and it keeps the generated reference
complete.

**6. Templates follow the template rules.** Every element carries its `ra-`
classes. No `<script>` tags — behaviour is declared with `data-ra-*`
attributes so it survives being inserted by AJAX. No hardcoded URLs — links go
through `UrlGenerator`, because the admin also runs in query-string mode. All
output is escaped with `$e()`; `$raw()` is the explicit, greppable exception.

## Getting set up

```bash
git clone https://github.com/tomac1/rockadmin.git
cd rockadmin
composer install
```

Tests that touch a database read their connection from environment variables
and skip themselves when those are absent:

```bash
export RA_TEST_MYSQL_DSN='mysql:host=127.0.0.1;dbname=rockadmin_test'
export RA_TEST_MYSQL_USER=root
export RA_TEST_MYSQL_PASSWORD=root

export RA_TEST_PGSQL_DSN='pgsql:host=127.0.0.1;dbname=rockadmin_test'
export RA_TEST_PGSQL_USER=postgres
export RA_TEST_PGSQL_PASSWORD=root
```

Both databases are created and torn down by the test suite. Point them at a
throwaway database — the suite drops and recreates tables.

## Before you open a pull request

```bash
composer run check     # coding standards, static analysis, tests
```

That runs PHP-CS-Fixer (PSR-12 plus a few rules), PHPStan at level max, and
PHPUnit. CI runs the same on PHP 8.4 and 8.5 against both databases.

Then work through the checklist in the pull request template. It is short and
every item corresponds to a constraint above.

## What makes a good pull request

- **One change per pull request.** A bug fix and a refactor in the same diff
  are hard to review and harder to revert.
- **Tests that would fail without the change.** For a bug fix, write the
  failing test first.
- **Documentation in the same commit.** For a configuration change that means
  a schema entry, which is what generates the reference.
- **A CHANGELOG entry** under `Unreleased`.

## Proposing a feature

Open an issue before writing code, especially for anything that adds
configuration. The most useful thing you can put in that issue is what the
configuration would look like — the shape of the config is the design, and it
is far cheaper to discuss before implementation than after.

Features are judged on whether they hold up under the constraints above, not
only on usefulness. "This would need a dependency" or "this only works on
MySQL" usually means the idea needs a different shape, not that it is
rejected.

## Coding style

- PSR-12, enforced by PHP-CS-Fixer; run `composer run cs:fix`
- `declare(strict_types=1)` in every file
- Types on every parameter, return and property; PHPStan runs at level max
- `snake_case` for configuration keys and database columns, `camelCase` for
  PHP methods and variables, `PascalCase` for classes
- Comments explain why, not what. Prefer a clear name over a comment.

## Reporting security issues

Do not open a public issue. See [SECURITY.md](SECURITY.md).

## Code of conduct

Participation is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).
