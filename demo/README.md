# RockAdmin demo

```bash
php -S localhost:8080 -t demo demo/index.php
```

Open <http://localhost:8080/>.

This is the design surface the default theme is tuned against — a themed
admin shell with a navbar, a menu, a page header with buttons, and a flash
message shown once as a toast. It is kept honest to the general case: it
loads a small but real `config/rockadmin.php` through the same
`RockAdmin\Config\Loader` a project uses, and wires the same `Kernel`,
`Router` and `Renderer` a project wires — nothing here is demo-only
machinery.

A real project is different: it is mounted through a Composer path
repository (or a tagged release), its `rockadmin.php` describes actual
pages, and its templates directory very likely overrides some of the SDK's
own. This demo is where the general case is enough; a real project is where
it stops being enough for one page and a template gets copied and edited.

Toggle your operating system's dark mode to see both themes;
`config/rockadmin.php` sets `theme.dark` to `'auto'`, which is what makes
that toggle work.

## The grid

`/p/ads` is a real grid, `demo/config/pages/ads.php`, over fixture tables:
a text column with a filter, a column joined from a `user` relation, money,
an enum shown as a badge, JSON, a sortable date, and a one-to-many of tags —
every feature milestone 6 built for the list region, in one page, the way
the reference documentation's worked example does.

It needs a database. Set the same variables the test suite reads —
`RA_TEST_MYSQL_DSN`, `RA_TEST_MYSQL_USER`, `RA_TEST_MYSQL_PASSWORD` (or the
`RA_TEST_PGSQL_*` equivalents for PostgreSQL) — then seed the fixture tables:

```bash
export RA_TEST_MYSQL_DSN="mysql:host=127.0.0.1;port=3306;dbname=rockadmin_test"
export RA_TEST_MYSQL_USER=root
export RA_TEST_MYSQL_PASSWORD=root
php demo/seed.php
php -S localhost:8080 -t demo demo/index.php
```

With no database configured, `/p/ads` still renders — the shell, the menu,
the page header — and says on the page that no database is configured,
rather than throwing a connection error at whoever only came to look at the
theme.
