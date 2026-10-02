# Milestone 11 — Operations, and the host's own workspace

Two things that look separate and are the same seam: a project supplying its
own logic for a write, and a project telling the admin which tenant a row
belongs to. Both are `bazik.cz-redesign` asking the same question — *this row
is not simply a row, it belongs to a world, and saving it means running my
code in that world.*

RockAdmin is going into Bazik on `admin28`, beside the existing `admin27`,
and neither of these exists today.

## Decisions this plan makes

**It is one interface, not two.** The first sketch had an `ActionPerformer`
for named actions and `WriteHandler` for form saves. That is two seams for
one idea, and it splits on an accident — that a form save has no action name.
There are five write paths (`create`, `update`, `delete`, a configured `post`,
and that `post` in bulk) and they all already pass through one place. So one
interface, keyed by page and operation, where the operation is `create`,
`update`, `delete` or the page's own verb.

**The specification does not describe this, and that is worth saying.** 7.4
lists the extension points as a `query` closure, `raw`, and a custom
`RowSource`; 7.7 says a project may replace `WriteHandler` wholesale or
override one method. Nothing is keyed by action. `WriteHandler` is the
nearest thing and it is not enough: `archive`, `publish` and `ban` all arrive
as `update($entity, $key, $values, $scope)`, so telling them apart means
guessing from the values. This milestone adds a seam the specification does
not have, which is a thing to do deliberately rather than quietly.

**It is not a custom controller**, which is what was first asked for. A
controller would take over the whole request and with it CSRF, the gate, the
`when` re-check, the scope, the transaction and the audit row — none of which
has anything to do with the project's own logic. Rule 7 says there is one
write path; this keeps it and hands the project the inside of it.

**The project declares it in configuration, not only in wiring.** A page file
that says `'handler' => '@handler:save_ad'` is a page file somebody can read
and know that saving is not the default. Registration alone would make the
configuration tell half the truth, and this project's whole argument for
configuration is that it is the thing you read to know what happens. The
registry is where the name is resolved, and a name nobody registered is
refused where the catalogue is — the rule `Roles` and `Permission` already
follow.

**Side effects cannot be rolled back, so there is an `afterCommit` hook.** An
operation that sends mail inside the transaction and then fails has sent mail
about a change that did not happen, and nothing anywhere says so. Bazik's
operations send mail. So `OperationContext` carries `afterCommit(Closure)`:
the write goes inside, the side effect is queued for after the commit. A hook
nobody uses costs nothing; a silent wrong mail costs a support ticket nobody
can answer.

**The host switches its own world, through `WorkspaceContext`.** Bazik's
services take the tenant from a `TenantService` singleton. The admin cannot
know that and must not learn it. It supplies the one fact it has — which
workspace this row is in — and the host does the switching, in its own
`try/finally`, the way `AdLifecycleRunner` already does.

**The row's workspace, not the active one.** Under "all workspaces" there is
no active workspace, and today the write is simply unscoped — correct for a
superadmin, and wrong for Bazik, because the mail then goes out under no
tenant at all. So the workspace is resolved *from the row* for every write,
and the superadmin editing one ad gets that ad's tenant without switching
first. Refusing writes under "all" was the other option; it would mean two
switches for one edit.

**Rendering counts, not only writing.** Bazik's `CategoryRepository::findOwned()`
follows the current tenant, so an edit form drawn under "all workspaces"
would offer the wrong categories. The form render is wrapped too. This came
from Bazik's own reading of its code and is the kind of thing the SDK would
not have discovered on its own.

**The resolver has three states, not two.** `switchTo(null)` means "all
workspaces" today, and there is no way to say "forget the override, follow
the host again". Bazik needs all three: a named tenant, none (global), and
automatic. Two of them collapse onto `null` right now, which is a bug waiting
for the first person who switches and then wants the default back.

## Pre-flight scan

**Five call sites, one file.** `ActionHandler` lines 393, 394, 567, 673, 751,
752 — bulk delete and update, a single configured action, the built-in
delete, and the form's insert and update. Every one of them is already inside
a transaction and already followed by `record()`, so the seam goes between
`$this->writes->` and the handler, and nothing about audit or transactions
moves.

**`SqlWriteHandler` already reads the row before writing.** `update()` and
`delete()` both call `find()` first, scoped. So "the row as it is now" is
available without a second read, and `WriteResult::$before` is already that
row.

**`RequestScope` already knows which column carries the workspace.**
`workspaceColumn()` and `workspaceLabel()` were added in milestone 10 for the
"all workspaces" grid column; resolving a row's own workspace is the same
question asked of one row.

**There is no `@handler:` reference.** `Resolver` has `page`, `action`,
`region` and `enum`. A fifth namespace is one more case in the same match.

## Global constraints

- **No runtime dependencies**, no framework coupling. The host implements
  `Operation` and `WorkspaceContext`; the core never learns what a
  `TenantService` is.
- **One write path.** An operation writes through `WriteHandler`. It does not
  get a `Connection`.
- **Every write still leaves an audit row**, from the `WriteResult` the
  operation returns.
- **Every configuration key is in the schema**, or `ReferenceIsCurrentTest`
  fails.
- **Both drivers.**

---

### Task 1: `@handler:` and the registry

**Files:** create `src/Config/HandlerReference.php`, `src/Data/Operations.php`;
modify `src/Config/Resolver.php`.
Test: `tests/Unit/Config/ReferenceTest.php`, `tests/Unit/Data/OperationsTest.php`.

A fifth reference namespace, parsed the way the other four are. The registry
maps a name to an `Operation`, and refuses a name nothing registered — at
build time, naming the page and the key, not at click time.

---

### Task 2: What an operation is

**Files:** create `src/Data/Operation.php`, `OperationContext.php`.
Test: `tests/Unit/Data/OperationTest.php`.

The interface, and what it is handed. `OperationContext` carries the page, the
operation's name, the bound scope, the active workspace, the row's own
workspace, and `afterCommit()`.

**What it may not have:** a `Connection`, a `Request`, or the gate. A write
outside `WriteHandler` is a write outside the audit log, and an operation
re-checking a permission is an operation that can get it wrong.

---

### Task 3: Declaring it

**Files:** modify `src/Page/ActionSchema.php`, `FormSchema.php`,
`PageRepository.php`.
Test: `tests/Unit/Page/OperationConfigTest.php`.

`'handler' => '@handler:save_ad'` on an action and on a form block. On an
action it makes `set` optional, which is the one load-time consequence; on a
form it changes nothing except what the file tells you.

**Refused at load:** a `handler` that is not a `@handler:` reference; a
`handler` and a `set` on the same action, since one of them would be ignored
and nobody would know which.

---

### Task 4: Dispatching through it

**Files:** modify `src/Http/ActionHandler.php`.
Test: `tests/Integration/Data/OperationFlowTest.php`.

All five call sites. The default is what happens today, expressed as an
operation so there is one path rather than a branch at each site.

**The test that matters:** a custom operation that writes through
`WriteHandler` and queues an `afterCommit`, and a second that throws. The
first leaves a row, an audit row and a side effect that ran *after* the
commit; the second leaves none of the three, including the side effect.

---

### Task 5: `WorkspaceContext`

**Files:** create `src/Auth/WorkspaceContext.php`; modify
`src/Auth/RequestScope.php`, `src/Http/ActionHandler.php`,
`src/Http/FormHandler.php`.
Test: `tests/Integration/Auth/WorkspaceContextTest.php`.

Resolve the row's own workspace; run the write, and the form render, inside
it.

**The test that matters:** under "all workspaces", editing a row in workspace
B runs the operation with B active, not with none and not with A.

---

### Task 6: Three states for the resolver

**Files:** modify `src/Auth/ActiveWorkspace.php`, `SessionWorkspace.php`,
`src/Http/WorkspaceHandler.php`, `templates/layout/base.php`.
Test: `tests/Unit/Auth/ActiveWorkspaceTest.php`.

A named workspace, none, and automatic. The switcher gains the third, because
somebody who has switched must be able to get back to the default without
knowing what the default was.

---

### Task 7: The recipe, and looking at it

**Files:** `docs/guides/`, `demo/`.
Test: `tests/Integration/Data/DemoOperationTest.php`, and a browser pass.

A documented recipe a project can follow — Bazik's shape, written out: an
`Operation` that switches nothing (the context did it), sends mail after the
commit, and a `WorkspaceContext` that wraps `TenantService`.

The demo gets one custom operation, so the seam is exercised by something
that ships rather than only by tests.

## Milestone acceptance

1. `composer run check` green with both databases.
2. A project can replace the logic of `create`, `update`, `delete` and any
   configured action, per page, without touching the core.
3. A custom operation still produces an audit row, still runs in the
   transaction, and still cannot write outside `WriteHandler`.
4. A side effect queued with `afterCommit` runs after the commit and does not
   run at all when the operation fails.
5. Every write, and every form render, happens with the row's own workspace
   active.
6. The resolver can be set to a workspace, to none, and back to automatic.
7. `handler` naming nothing registered is refused at build, naming the page.
8. Every new key is in the generated reference.

## What this milestone deliberately leaves out

- **A `Connection` for operations.** The way to write is `WriteHandler`.
  Handing out a connection would make rule 7 advisory.
- **Replacing the gate, the CSRF check or the `when` re-check.** Those are
  the parts a project must not be able to get wrong.
- **Mail itself.** Section 10 is the next milestone; this one only has to not
  be in its way.

---

## Where it ended up (2026-10-02)

All seven tasks done, `composer run check` green on both drivers (2476 tests),
and the demo exercises the seam with something that ships:
`demo/src/ArchiveAd.php`, named by `demo/config/pages/ads.php` as
`@handler:archive_ad`.

Against the acceptance list: a project can replace the logic of `create`,
`update`, `delete` and any configured action per page (2); a custom operation
still writes an audit row, still runs inside the transaction and still cannot
write outside `WriteHandler` (3); `afterCommit` runs after the commit and does
not run at all when the operation fails (4) — carried by
`tests/Integration/Data/OperationFlowTest.php`, whose sharpest case is a bulk
action whose *second* row raises and whose first row's side effect therefore
never runs; every write and every form render happens in the row's own
workspace (5), carried by `WorkspaceContextTest`; the resolver has three states
and can be set back to automatic (6); and a `handler` naming nothing registered
is refused when the registry is built, naming the page (7).

### What the plan got right, and what it cost

**One interface for all five write paths was the right call and cost almost
nothing.** The alternative — an `Operation` for actions and something else for
forms — would have been a split on an accident of naming, and the five paths
already met in one class.

**The seam promised an answer it could not give.** `Operation`'s docblock said
a `ValidationError` carries a message back to the form; `ValidationError` is a
value object and was never throwable. `OperationRefused` is what that sentence
was describing: it rolls everything back and then answers where the person is
— the form redrawn, a flash for an action, a skipped row with its own reason
inside a selection. Without it, "this ad is already sold" would have had to be
a 500.

**"Raising aborts everything" was false for four of the five paths.** Only a
bulk action had a transaction around it; a form save called `WriteHandler`,
which commits before it returns, so an operation that wrote and then raised
left the row behind. `apply()` now opens one transaction around the whole
operation — not around each of its writes — so an operation free to write three
times is an operation whose three writes are one unit.

**`$row` nearly arrived useless.** Read as "the columns being written", it is
empty for exactly the case that most wants it: an action replaced by an
operation carries no `set`, so the operation would have been handed a row
consisting of its own key. It reads the page's form columns too.

### Two things found by running it

**The switcher's new entry lied about itself.** "Automatic (Motobazar.cz)" was
drawn after choosing Motobazar — but automatic would have resolved to
Cyklobazar, so the one label on the control that claimed to predict an outcome
predicted the wrong one, and promised that pressing it changes nothing. It
names the workspace only while automatic *is* the current state, where
`current()` is the automatic answer. Found in the browser, not by a test; the
test that now holds it was written afterwards.

**The demo's `archive` had no test at all before this milestone.** Nothing in
the suite posted it, so swapping its `set` for a handler broke nothing and
proved nothing. `DemoOperationTest` covers it now, including the refusal and
the bulk skip.

### Still open

**Narrow viewports do not work, and now there is a measurement.** At 384 CSS
pixels the ads page scrolls horizontally to 1086: the grid table is 1070 wide
with no scroller of its own, so the *page* scrolls and the navbar goes with it,
and the top bar's own contents measure 888 inside a 384 box rather than
wrapping. This was milestone 9's carried debt, unverified until now because
window resizing did not reach the page viewport through the tooling; it does
now. It is a design-system question — `docs/design-system/` is the authority on
how this should look — and it wants an interlude of its own rather than a
corner of the next milestone.

**Nobody has switched JavaScript off yet.** Still carried from milestone 9.

**The `Host`-header resolver still has no implementation here**, which is
correct — it belongs to the host — but `docs/guides/own-write-logic.md` now
writes out the shape Bazik needs, including the three states its
`ActiveWorkspace` has to express, so the next thing to test it is Bazik itself.
