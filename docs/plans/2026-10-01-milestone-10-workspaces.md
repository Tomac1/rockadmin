# Milestone 10 — Workspaces

Specification 9.3. Deferred twice, in milestone 5's plan and again in
milestone 8's, with the same reason both times: the first real project has one
site, so workspaces would be a subsystem nobody uses. That reason is now
false — `bazik.cz-redesign` is multi-tenant and RockAdmin is going into it —
and the deferral was always explicitly a deferral rather than a decision.

## Decisions this plan makes

**This is the security milestone, and the shape of it is one sentence: the
scope is not a filter.** 9.3 says so and gives the reason — "were the scope
merely a default filter value, a user could edit it in the URL and read
another workspace's data". Everything below follows from taking that
seriously, including the parts that are inconvenient.

**Writes are the hole, and they are a breaking interface change.**
`SqlWriteHandler` builds `UPDATE … WHERE id = ?` and `DELETE … WHERE id = ?`.
With one workspace that is correct. With two, `POST /a/ads/update` carrying
another tenant's `_id` writes across the boundary, and nothing anywhere
notices — the gate passed, the token was valid, the row exists. 9.3 says the
third check applies "to update, delete and preview as well as list", and the
only way to mean it is for `WriteHandler` to take the scope. That changes an
interface a host implements (rule 2), which is a thing this project does not
do lightly; it is done here because the alternative is an interface that
cannot be implemented safely.

**A scoped write that matches nothing is a 404, not a success.** `UPDATE …
WHERE id = ? AND tenant_id = ?` affecting zero rows is indistinguishable from
a no-op write unless somebody checks. It has to be checked, and it has to
answer the way a missing row answers, because from the asker's side that is
what it is.

**The active workspace is session state, with a seam for hosts that decide it
another way.** 9.3 puts it in the session and switches it from the navbar,
which is right for an admin serving several tenants. Bazik resolves its
tenant from the `Host` header instead, per request, in its own
`TenantService` — so a host must be able to bind the active workspace rather
than having it switched. One interface, two ways in; the session-backed one
is the default and the switcher drives it.

**`{{workspace.*}}` binds to a value and never to SQL.** 9.3 is explicit and
`QueryBuilder::assertBound()` already refuses an unbound placeholder reaching
a query — written two milestones ago against this moment. This milestone
supplies the binding, and that refusal becomes the backstop rather than the
only line.

**"All workspaces" is a permission, not a convenience.** It appears only for
somebody holding every workspace, the scope is omitted, and the grid gains a
workspace column so rows stay attributable. A grid showing two tenants' rows
with nothing saying which is which is worse than no "all" at all.

**Milestone 7's open hole closes here.** A hidden field's default is
reapplied on create only, which that milestone recorded as "safe until a row
scope check exists". It exists now: a create must take its workspace columns
from the active workspace rather than from the submission, or the first thing
anybody does is post a create with somebody else's `tenant_id`.

## Pre-flight scan — what exists and what does not

Run against the code before dispatching anything.

**More is built than the milestone count suggests.** `AuthUser` carries
`workspaces` and answers `hasWorkspace()`. `Gate` declares
`workspace.switch`. `Resolver` already defers `{{workspace.*}}` into a
`Placeholder` rather than resolving it. `PageDefinition` carries `scope` and
`Query` carries it into `QueryBuilder`, which refuses it unbound. `ShellView`
carries `WorkspaceView`s and `layout/base.php` draws the switcher — the
interlude drew it deliberately unwired, on the grounds that the markup costs
nothing while it waits. What is missing is the middle: the workspaces
themselves, the binding, and the three checks.

**There is no `/w/{workspace}` handler.** The route is in the router and the
gate; nothing is registered for it, so it is a 404 after the gate — which is
safe but is not a switcher.

**The scope is on the page, not on the workspace.** A page declares
`'scope' => ['tenant_id' => '{{workspace.tenant_id}}']`. That is the right
way round: the page knows which of its columns is the tenant, and the
workspace knows what the value is. Nothing needs to change about it except
that the placeholder now resolves.

**Bazik's shape, read from its own configuration rather than imagined.** Two
tenants in one database, `tenant_id` on every relevant table, resolved per
request from the `Host` header; tenants are keyed by integer and carry a
`domain` list, a `name` and an `active` flag. So a RockAdmin workspace maps
to a Bazik tenant with `vars: ['tenant_id' => 1]`, and the `active` flag is
the host's business rather than this schema's.

## Global constraints

- **No runtime dependencies**, no framework coupling. The host supplies the
  active workspace through an interface, as it already supplies sessions,
  authentication and writes.
- **The scope reaches `WHERE`, never a filter and never a URL.** A test
  asserts a request cannot remove it, and that is the test this milestone is
  for.
- **Every configuration key is in the schema**, or `ReferenceIsCurrentTest`
  fails.
- **Both drivers.** Rule 3 is only checked when both run.
- **No native browser dialogs** (rule 6, as of this week).

---

### Task 1: What a workspace is

**Files:** create `src/Auth/WorkspaceDefinition.php`,
`WorkspaceSchema.php`, `Workspaces.php`; modify `src/Config/Loader.php`.
Test: `tests/Unit/Auth/WorkspaceSchemaTest.php`.

`config/rockadmin/workspaces.php`, read the way `roles.php` is: stored raw by
the loader, built and validated by whoever uses it, because the loader has no
page repository and giving it one would be the layer inversion `Gate` already
refuses.

Keys: `label`, `vars`, `pages`. Refused at load: a `pages` entry naming no
page; a `vars` value that is not a scalar; an empty `workspaces.php` that
exists, which means somebody started and stopped.

**A page in no workspace at all is the question this task must answer**, and
it is not obvious: it could mean "visible everywhere" or "visible nowhere".
Pick one, say why, and make the other impossible to get by accident.

---

### Task 2: Which workspace is active

**Files:** create `src/Auth/ActiveWorkspace.php` (interface),
`SessionWorkspace.php`; modify `src/Auth/Identity.php`.
Test: `tests/Unit/Auth/ActiveWorkspaceTest.php`.

The interface a host implements, and the session-backed default. Resolved per
request and memoised, the way `Identity` already memoises the user.

**A user who holds one workspace is in it**, with no switch and no choice to
get wrong. A user who holds none is a user who can see nothing — which is a
configuration mistake and must say so, not silently show everything.

**A session naming a workspace the user no longer holds is ignored**, not
honoured: access is revoked by changing the user, and a stale session must
not outlive it.

---

### Task 3: Binding the placeholder

**Files:** create `src/Config/Binder.php`; modify `src/Page/PageRepository.php`
or whoever hands a `Query` its scope.
Test: `tests/Unit/Config/BinderTest.php`.

`{{workspace.tenant_id}}` becomes the active workspace's `tenant_id`.
`{{user.id}}` becomes the signed-in user's. A placeholder naming a variable
the workspace has not got is refused loudly — it would otherwise bind to null
and scope the query to `WHERE tenant_id IS NULL`, which is a silent empty
grid, or worse, no condition at all.

The test that matters: `QueryBuilder::assertBound()` still fires. It is the
backstop for everything this task gets wrong, and a test proving it is
reachable is worth more than one proving the happy path.

---

### Task 4: The three checks

**Files:** modify `src/Auth/Gate.php`, `src/Http/Kernel.php`,
`src/Grid/QueryFactory.php`, `src/Db/WriteHandler.php`,
`src/Db/SqlWriteHandler.php`, `src/Form/FormRegion.php`,
`src/Db/RowReader.php`.
Test: `tests/Integration/Auth/WorkspaceScopeTest.php`.

1. The page permission — unchanged.
2. The page is listed in the active workspace — a new refusal, and it is a
   403 rather than a 404: the page exists and this reader may hold the
   permission; what they do not have is it *here*.
3. The row is inside the scope — on every read **and every write**.

**`WriteHandler` takes the scope.** `update()` and `delete()` gain it, and
the SQL grows `AND <column> = ?`. Zero affected rows becomes
`NoSuchRowException`, which the handler already turns into a 404.

**The test this milestone exists for:** two workspaces, a row in each, and a
user holding one. Then, as that user: list, preview, edit, update, delete and
bulk-delete the *other* workspace's row, by id, by URL, by posted body. Every
one answers 404 or 403 and changes nothing. Written as a loop over every
route that touches a row, so a route added later and forgotten is a failing
test rather than a hole.

---

### Task 5: Switching, and "all workspaces"

**Files:** create `src/Http/WorkspaceHandler.php`; modify
`templates/layout/base.php`, `src/View/ShellView.php`, `src/Grid/ListRegion.php`.
Test: `tests/Unit/Http/WorkspaceSwitchTest.php`.

`POST /w/{workspace}`, CSRF-checked, refusing a workspace the user does not
hold. A POST for the reason 5.1 gives and which is worth repeating: browsers
prefetch links on hover, and a GET would let a hover over the switcher
quietly change what somebody is looking at.

"All workspaces" appears only for a user holding every one. The scope is
omitted and the grid adds a workspace column — which has to come from
somewhere, and the honest answer is the scope's own column, since that is the
only column known to identify the workspace.

**The switcher is already drawn** and unwired. Wiring it is the small half;
the large half is that it must not offer a workspace the user does not hold,
and must not offer "all" to somebody holding two of three.

---

### Task 6: Creating into a workspace

**Files:** modify `src/Http/ActionHandler.php`, `src/Form/FormRegion.php`.
Test: `tests/Integration/Form/WorkspaceWriteTest.php`.

Milestone 7's recorded hole. A create takes its scope columns from the active
workspace, after validation and over anything submitted — a form in a browser
is editable, and a hidden input carrying `tenant_id` is a suggestion.

An update must not change them either. Moving a row between workspaces is a
real operation and not this one; if it is ever wanted it is an action with its
own permission, not a side effect of editing a title.

---

### Task 7: Looking at it

**Files:** `demo/config/`, `demo/seed.php`, `demo/README.md`.
Test: `tests/Integration/Form/DemoWorkspaceTest.php`.

Give the demo two workspaces over the existing ads table, a user in one and a
user in both, and **drive it in a browser and report what was measured**:
switching, the grid changing underneath, the workspace column appearing under
"all", and — the one that matters — what happens when the URL of a row in the
other workspace is typed in by hand.

Seven defects in this project have shipped green and been found by opening the
page. The last one had been 500ing the detail page of every row for a
milestone.

## Milestone acceptance

1. `composer run check` green with both databases.
2. A user holding one workspace sees only its rows, on every page, with no
   configuration beyond `scope` and `workspaces.php`.
3. That user cannot read, edit, update, delete or bulk-delete a row in
   another workspace by any route, including by typing its id into the URL.
4. A page not listed in the active workspace is 403, and absent from the menu.
5. Switching is a POST, CSRF-checked, and refuses a workspace the user does
   not hold.
6. "All workspaces" appears only for a user holding every workspace, and the
   grid then says which workspace each row is in.
7. A create cannot place a row in a workspace the creator does not hold, and
   an update cannot move one.
8. An unbound `{{workspace.*}}` still cannot reach a query.
9. Every new key is in the generated reference.

## What this milestone deliberately leaves out

- **Per-row and per-column permissions** (13, "out of v1"). The scope is a
  workspace boundary, not a row ACL, and conflating them would put a
  permission check in `WHERE` where nobody would find it.
- **Moving a row between workspaces.** A real operation, with its own
  permission, and not a side effect of editing.
- **Workspace-scoped admin users.** 9.3 says admins are not tenant-scoped;
  they are granted access to workspaces. That distinction is the whole design
  and is not quietly reversible.

---

## Where it ended up (2026-10-01)

All seven tasks done. The milestone's own claim — the scope is not a filter —
is carried by `tests/Integration/Auth/WorkspaceScopeTest.php`, which points
every route that touches a row at another workspace's row and checks the
database afterwards with plain SQL, on both drivers.

### What the plan got right, and what it cost

**The write hole was real and worse than the plan said.** `SqlWriteHandler`
built `UPDATE … WHERE id = ?`, so a posted `_id` from another tenant passed
the gate, carried a valid token and named a row that existed. Closing it
meant a required argument on `WriteHandler`, which breaks every
implementation — four of them, all audited. The scope is applied twice per
write, to the read and to the statement: a boundary enforced only by the read
before it is a boundary with a gap.

**The open question answered itself once the costs were written down.** A
page no workspace lists is a page nobody sees, because the two readings are
not symmetric — the other one hides one tenant's page inside another's.

### Three things found by running it

**A bulk action cancelled itself on an unreachable row.** The scoped write
raised, and atomicity rolled back the rows the person could perfectly well
touch. A row you cannot see is not a failure; it is a row that is not there,
the same as one somebody deleted a moment ago. Both are skipped and counted.

**`scopeFor()` returning `[]` when unwired was a hole of its own**, and three
failing tests caught it. A missing collaborator must not turn into an
unscoped query. It passes the page's own scope through instead, so a literal
scope still applies and an unbound placeholder is refused — loud instead of
open.

**PostgreSQL's identity sequence does not notice explicit ids.** A fixture
inserting rows 1 and 2 by hand left the sequence at 1, so the create
collided. MySQL's AUTO_INCREMENT moves itself. Exactly the kind of difference
rule 3 exists to surface, found because the suite runs both.

### Still open

**The `Host`-header seam has no implementation here.** `ActiveWorkspace` is
the interface Bazik would implement to resolve its tenant per request, and
only the session-backed one ships. That is correct — a host's own resolution
belongs to the host — but nobody has written one against the interface yet,
so its shape is untested by use.

**Milestone 9's two open items are still open**: nobody has switched
JavaScript off, and narrow viewports remain unverified because window
resizing does not reach the page viewport through the tooling available.
