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

## The form

The same file declares a `form` region, so the grid's **New ads** button and
each row's **Edit** link lead somewhere: `/p/ads/create`, `/p/ads/{id}/edit`
and `/p/ads/{id}/copy` draw it, and `POST /a/ads/{create,update,delete}` is
the only thing in the admin that changes a row.

Six of the eleven field types are on it, which is every one this table can
honestly carry — text, textarea, number, a select whose options come from
`config/enums.php` through the same `@enum:ad_state` reference the grid's
badge and filter read, a checkbox and a date — plus a hidden `created_at`
with an `@now` default, applied when the row is saved and never drawn as a
control. `copy.reset` names `published_on`, so a copy carries everything
except the publication date, which starts at today's default again.

Things worth doing once, in a browser, because each of them was a defect at
some point in this project's history:

- Save a new ad: the toast, the redirect, and the row at the top of the grid.
- Filter the grid, go to page 2, edit a row and save: you come back to that
  same filtered page, not to page 1. The link carries the grid's state as
  `_ret`, which is validated as an internal path and never trusted verbatim.
- Submit something invalid: the page comes back at 422 with every error at
  once, each one against its field and in the summary at the top, and what
  you typed still in the inputs.
- Turn JavaScript off and do all of it again. Nothing here needs a script —
  `core.js` only adds the confirmation in front of Delete, and the server
  never treats that attribute as evidence that anybody agreed.
- Toggle your operating system's dark mode on the form. Every colour on it
  comes from a token that is redefined for dark mode; an accent that is legible
  as a button fill is not automatically legible as text, which is why the
  outline buttons and the required marker read `--ra-fg-*` and not
  `--bs-danger`.
