# Milestone 8 — Actions

Specification 8.5, the half of it that navigates and changes data. A grid
that can only be read is a report; this is what makes it an admin.

## Decisions this plan makes

**Workspaces are still not next, and this reverses what milestone 5's plan
said.** That plan deferred them and wrote "next milestone". It was wrong, for
a reason that only became visible once the admin was running over a real
project: workspaces solve multi-tenancy, and the first project has one site.
Building them now means a subsystem nobody uses, while four real pages stay
read-only and nothing can be deleted from a grid. Milestone 7's note — a
hidden field's default is reapplied on create only, which is safe only once
the row scope check exists — is a hole *when workspaces exist* and a note
until then.

**`open` actions are not in this milestone.** Specification 8.5 defines three
types: `link`, `open` and `post`. `open` loads a region into a modal or an
offcanvas, which is a client-side subsystem — focus management, escape
handling, a fragment arriving into a live document, and `@region:` addressing
across pages. It wants its own milestone and its own browser pass. `link` and
`post` are the two that need no new JavaScript beyond a confirmation, and
between them they carry delete, which is the thing a real admin is missing
today.

**`preview` stays a region, and does not become an action yet.** 8.5 says a
preview is "only shorthand" for a region plus an `open` action. Half of that
shorthand — the region — shipped in milestone 6. The other half is an `open`
action, so it arrives with them. Nothing changes here; this is recorded so the
shorthand is not half-built twice.

**A reference is resolved the way `@enum:` already is.** `Resolver` matches
`@enum:(\w+)` and produces an `EnumReference`. `@page:`, `@action:` and
`@region:` are the same shape and get the same treatment — one resolver, four
reference types, not a second parsing mechanism with its own rules about what
a key may contain.

**`target` accepts only a reference, never a bare URL.** 8.5 says so and gives
the reason: a raw URL cannot be permission-checked against a page, and would
become the obvious way to smuggle content into the admin frame. The refusal
is at load.

**Row placeholders are not configuration placeholders, and the loader must
not let them be confused.** `{{user.id}}` resolves once, at load, from the
signed-in administrator. `{user_id}` resolves per row, from a column of that
row. Writing the first when you meant the second produces the logged-in
administrator's id on every row — and looks correct in testing, because the
developer is usually looking at their own records. The two are parsed by
different code at different times and that is the whole safeguard.

**Bulk actions are atomic, and that needs something milestone 7 left open.**
`ActionHandler` holds no `Connection` — a deliberate decision then, recorded
as the seam a bulk action would have to open. It opens here: a bulk action
wraps its loop in one `Connection::transaction()`, which is depth-counted and
joins the handler's own, so forty rows succeed or none do. Without it a
failure at row forty leaves a half-applied action and no way to tell which
half.

## Pre-flight scan — three things this plan assumed wrongly

Run against the code before dispatching anything.

**The gate refuses a verb it does not know, and that is deliberate.**
`Gate::ACTION_PERMISSIONS` is a closed set of `create`, `update` and `delete`,
and its comment says why: without it, `POST /a/ads/publish` would ask for a
permission named `ads.publish`, and the day somebody added a `publish` action
it would silently start being granted by `ads.*`. So a configured action named
`archive` is refused by the gate before any handler sees it, and Task 5's "a
configured `post` action joins them" cannot work as written.

The fix is not to open the set. An action declares its own `permission` (Task
1), so the gate asks the page: for the verb `archive`, the permission is
whatever that page's `archive` action declares, and a verb no action declares
is still refused. That makes the closed set closed over *configuration* rather
than over three literals, and keeps the property its comment is protecting —
a new verb grants nothing until somebody says what it needs. **Task 5 owns
this, and it is the task's main design decision rather than a detail.**

**`Resolver` cannot validate a `@page:` reference, for the same reason
`Loader` could not validate a role.** It lives in `Config` and is constructed
with the environment and the raw array; pages live in `Page`, and giving the
configuration layer a page repository would have it reach into the layer that
consumes its output. So `@page:` and `@action:` are *parsed* into reference
objects there — the way `@enum:` produces an `EnumReference` — and *validated*
in `PageRepository`, where the catalogue exists. Task 2's "refused at load,
naming the nearest" happens in the repository, not the resolver.

**`UrlGenerator` has only `to()` and `route()`.** A reference resolves to a
URL through one of those two, so Task 2 adds a method that takes a reference
rather than teaching every caller to unpack one. Named here because the task
said "modify `UrlGenerator`" without saying what it was missing.

## Global constraints

- **No runtime dependencies**, and no new JavaScript library. A confirmation
  is `data-ra-confirm` bound by delegation, as rule 6 requires.
- **One write path.** Every `post` action, single or bulk, goes through
  `WriteHandler` and leaves an audit row. A bulk delete of forty rows writes
  forty audit rows, not one: the log answers "what happened to this row",
  and a row whose deletion is recorded only in a summary cannot answer it.
- **The gate decides what is drawn.** An action the reader may not perform is
  not rendered, through the same `Gate::allows()` the kernel calls. Milestone
  5 built that for create, edit and delete controls; actions join it rather
  than growing a second rule.
- **Every configuration key is in the schema**, with description and example,
  or `ReferenceIsCurrentTest` fails.
- **Templates** carry `ra-` classes, escape everything, and hardcode no URL.

## Test environment

Both drivers exported as `CLAUDE.md` records them. Rule 3 is only checked when
both run. Note that the demo and the test fixtures share `rockadmin_test`, so
a suite run wipes the demo's data — `demo/serve.bat` says so and reseeds.

---

### Task 1: What an action is

**Files:** create `src/Page/ActionType.php`, `ActionSchema.php`,
`ActionDefinition.php`; modify `src/Page/PageSchema.php`.
Test: `tests/Unit/Page/ActionSchemaTest.php`.

`ActionType` is `Link` and `Post` — `Open` is declared with the others and
refused at load with a message naming the milestone that builds it, because a
silent "unknown type" for something the specification documents is worse than
saying "not yet".

Keys, each needing a description written for somebody deciding whether to set
it: `type`, `label`, `icon`, `to`, `permission`, `confirm`, `when`, `refresh`.

**What is refused at load:** a `to` that is not a reference; a `post` action
with no `confirm` (a POST that changes data and asks nothing is a mis-click
away from a deletion, and 8.5 says always confirmed); a `when` naming a column
the region does not declare; a `permission` outside the generated catalogue.

---

### Task 2: Resolving a reference

**Files:** create `src/Config/PageReference.php`, `ActionReference.php`;
modify `src/Config/Resolver.php`, `src/Http/UrlGenerator.php`.
Test: `tests/Unit/Config/ReferenceTest.php`.

`@page:ads`, `@page:ads/{id}/edit`, `@action:archive`. Read how `@enum:` is
matched and produce the same shape of object. A reference to a page that does
not exist is refused at load, naming the nearest.

**`@region:` is parsed and refused here**, with a message saying `open`
arrives later. Parsing it now means the refusal is specific rather than
"unknown reference", and means Task 1's `target` validation has something real
to reject.

---

### Task 3: Row placeholders

**Files:** create `src/Grid/RowPlaceholders.php`.
Test: `tests/Unit/Grid/RowPlaceholdersTest.php`.

`{column}` substituted per row while rendering. **URL-encoded on substitution,
and only keys present in the region's data are accepted**, so a row cannot
inject a path. A key the row does not carry is refused loudly at render rather
than substituted empty — an action pointing at `/p/ads//edit` is a 404 whose
cause is invisible.

The test that matters: a row whose value is `../../etc/passwd`, and one
containing `?`, `#` and a space, produce a URL that still addresses the row
the action was drawn for.

---

### Task 4: Where actions appear

**Files:** modify `src/Page/ColumnType.php` (an `actions` case),
`ColumnSchema.php`, `PageRepository.php`; create `src/Grid/ActionView.php`.
Test: `tests/Unit/Page/ActionColumnTest.php`.

A column of type `actions` with an `items` list, and a page `header.buttons`.
`items` accepts the three forms 8.5 lists: a named action, a named action
adjusted inline, and one defined inline.

**An action column is an ordinary column**, placed among the others and named
for what it holds. The key becomes the CSS class and the template override
path, so `tools` is worth more than `actions2`.

`ListView::hasRowActions()` already decides whether the table draws its own
trailing actions cell. An `actions` **column** is a different thing, and the
two must not both claim the same cell — milestone 7 shipped a header and body
that disagreed about cell count twice, and this is a third way to do it.

---

### Task 5: Performing one

**Files:** modify `src/Http/ActionHandler.php`; create
`src/Http/ActionRequest.php`.
Test: `tests/Unit/Http/CustomActionTest.php`,
`tests/Integration/Form/ActionFlowTest.php`.

`POST /a/{page}/{action}` already carries `create`, `update` and `delete`.
A configured `post` action joins them: CSRF, gate, `when` re-checked on the
server, write through `WriteHandler`, audit row, flash, redirect.

**`when` is re-checked server-side.** It decides whether the action is
*offered*; a client that did not draw it can still post it.

---

### Task 6: Many rows at once

**Files:** modify `src/Grid/ListRegion.php`, `ListView.php`,
`src/Http/ActionHandler.php`, `templates/region/list/`, `assets/js/core.js`;
modify `src/Page/PageSchema.php` for `bulk_actions`.
Test: `tests/Integration/Form/BulkActionTest.php`.

A selection column, a bulk bar that appears with a selection, and one
transaction around the loop.

**`ActionHandler` takes a `Connection` here** — the seam milestone 7 recorded
and declined to open. One transaction, depth-counted, so forty rows succeed or
none do.

**The selection is a list of keys the server re-checks**, not a promise. Every
key is gated and `when`-tested individually; a selection is an argument, not
an authorisation.

Worth deciding and saying: what a partial failure reports. Rolling back and
reporting "nothing happened, row 17 refused" is honest and loses the other
thirty-nine; the alternative is not atomic. The plan says atomic; the brief
should make the implementer argue it rather than assume it.

---

### Task 7: Looking at it

**Files:** `demo/config/pages/ads.php`, `demo/README.md`, the real project's
pages. Test: `tests/Integration/Form/DemoActionsTest.php`.

Give the demo a delete action, an archive action with a `when`, and a bulk
delete. **Then drive it in a browser and report what was measured:** a viewer
sees no action column at all; an editor sees delete and not archive on a row
whose state does not match; confirming and declining; a bulk delete of three
rows leaving three audit rows; the whole flow with JavaScript disabled, where
a confirmation is the browser's own and a bulk action posts an ordinary form.

Five milestones have now shipped a defect that passed every test — an
invisible toast, a grid with no links, a toast covering the navigation, a
configured placeholder that never rendered, a navbar that stood on end, a
single-layout page using half its width. Every one was found by opening it.

---

## Milestone acceptance

1. `composer run check` green with both databases.
2. A row is deleted from a grid, from configuration alone.
3. A bulk action over forty rows is one transaction: a refusal at row forty
   leaves nothing applied.
4. Every `post` action is CSRF-checked, gate-checked and `when`-checked on the
   server, whatever the client drew.
5. A row placeholder cannot produce a URL addressing another row.
6. An action the reader may not perform is not drawn, and 403 if posted.
7. Every write leaves its own audit row, bulk included.
8. Everything works with JavaScript disabled.
9. Every new key is in the generated reference.

## What this milestone deliberately leaves out

- **`open` actions**, modals and offcanvas (8.5) — their own milestone.
- **`@region:` addressing** — parsed and refused, so the refusal is specific.
- **Workspaces** (9.3) — still deferred, now with a reason that is about this
  project rather than about sequencing.
- **Inline cell editing** (8.12) — reserved for v1.1 by the specification.
