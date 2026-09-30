# Milestone 5 — Identity and permission

Steps 3, 4 and 7 of the lifecycle: who is asking, are they logged in, and may
they do this. Plus the record of what they did.

Built after milestones 6 and 7 rather than before them, which was the right
order — a gate over pages that did not exist yet would have been guesswork —
but it means the admin has spent three milestones open to anybody who knows
the URL. The first real project mounting it discovered exactly that.

## Decisions this plan makes

**Workspaces are not in this milestone.** Specification 9.3 is a subsystem of
its own: a scope applied to `WHERE` at query-build time, a switcher in the
navbar, an "all workspaces" mode that adds a column to every grid, and three
checks that must all pass. It touches `QueryFactory`, `PageRepository`, every
region and the shell. Bolting it onto the end of an authentication milestone
would get it half-built, and half a tenancy boundary is worse than none
because it reads as if it works. It gets its own milestone, next.

One thing must travel with that decision, because milestone 7 left it
recorded and it is still true: **a hidden field's default is reapplied on
create only, and that is safe only once the scope check exists.** Nothing is
scoped today, so this is not a hole now. It becomes one the moment workspaces
ship without the row check, and the fix belongs there, not here.

**Password reset is not in this milestone.** It needs `Mailer`, which does not
exist. `ra_password_resets` is created here anyway, because creating a table
is cheap and a second migration against a live installation is not.

**Migrations get a minimal runner, not the CLI.** Nothing in this milestone
works without `ra_users`, and specification 11 puts the CLI in a later
milestone. So `Migrator` is a class that applies the SDK's own `.sql` files in
order and records them in `ra_migrations`; a project calls it from a one-line
script, and the CLI wraps the same class later. Writing the DDL by hand into
each project's own migration system would fork the schema per host, which is
the one thing the `ra_` prefix exists to prevent.

**Throttling is a table, not the session.** An attacker guessing passwords
discards cookies between attempts, so a session counter throttles only honest
users. `ra_login_attempts` is keyed by email *and* by IP, and both are checked:
by email alone, one attacker locks out a real administrator at will; by IP
alone, a botnet walks straight past it.

**The gate is consulted twice, by one method.** Once in the kernel before a
handler runs (step 7), and once while rendering, so a forbidden menu item or
button is never drawn. Specification 9.2 is explicit that a visible button
returning 403 is the wrong behaviour. Two call sites, one `Gate::allows()` —
never a second copy of the rule that decides what rendering may show.

**The gate fails closed.** An action nobody declared a permission for is
denied, not allowed. A page added to configuration brings its own permissions
(9.2), so the only way to reach an undeclared permission is a bug or a
hand-written URL, and both should be refused.

**Admin users are not the project's users.** Specification 9.1: five admins
against a hundred thousand customers, different fields, different security
properties, and the project must stay free to restructure its own `users`
table. `Authenticator` is an interface precisely so a project that wants the
other thing can have it.

**`auth_provider` and `auth_subject` are columns from the first migration**
even though nothing reads them, so that adding OIDC in v1.1 needs no migration
against a live table.

## Pre-flight scan — three things this plan assumed wrongly

Run before dispatching anything, against the code rather than against memory.
All three are corrected in the tasks below; they are recorded here because the
interface block of a plan is where my errors live, in every milestone so far.

**The routes already exist.** `Router::ROUTES` declares `login`, `logout`, all
four `password.reset.*`, `workspace.switch`, `_setup` and `_diagnostics`
today. Task 4 does not add routes; it registers handlers for routes that have
been sitting there unhandled since milestone 1. Several of those routes still
have no handler after this milestone, which Task 6's registry test has to
account for rather than trip over.

**`ROUTES` is private, so Task 6's central test cannot be written.** The
acceptance criterion "every registered route names a permission or is a named
exception" needs to enumerate the table. Task 6 adds a public accessor for the
route names; without it that test is impossible and the criterion is decorative.

**There is no 401.** `src/Http/` has `HttpException`, `NotFoundException` and
`ForbiddenException` and nothing else, and no code anywhere produces a 401.
Task 3 needs `UnauthorizedException`, which my file list omitted.

## Global constraints

Every task inherits these. They are not repeated in each brief.

- **No runtime dependencies.** Argon2id is `password_hash()` with
  `PASSWORD_ARGON2ID`, which is PHP itself. No package solves anything here.
- **No framework coupling.** The core never mentions Laravel. `SessionStore`
  already exists as the seam; `Authenticator` becomes the second one.
- **MySQL and PostgreSQL both work from the same configuration.** DDL
  differences live in `Dialect`, which already carries `returningClause()` and
  `quoteIdentifier()`.
- **Every configuration key exists in the schema**, with type, default,
  description and example, or `ReferenceIsCurrentTest` fails.
- **Template rules.** `ra-` classes, no `<script>` bodies, no hardcoded URLs,
  everything escaped.
- **One write path.** The audit log is written in one place, not copied into
  each caller. **Corrected during execution:** this said "hooks
  `WriteHandler`", which reads as instructing the hook into the data layer and
  contradicts rule 4 — an audit row needs the acting user and the page, and a
  `WriteHandler` is handed an `Entity`, which names a table. Two pages may sit
  over one table, so a table is not a page. Rule 4 wins: the hook is in
  `ActionHandler`, the one HTTP write path, and the constraint means what it
  now says. The cost is that a write arriving from somewhere other than HTTP
  records nothing; the fix, if it is ever needed, is a `WriteContext` value
  passed through `WriteHandler` as an argument, so the data layer carries it
  without knowing what is in it.
- **The gate is not optional.** No handler may be reachable without passing
  through it.

## Test environment

Both servers, exported as `CLAUDE.md` records them — note `_PASSWORD`, not
`_PASS`. A DSN that is set but unreachable fails rather than skipping, and the
MySQL session runs with a strict `sql_mode`, so the suite tests the world it
deploys into.

Rule 3 is only actually checked when both drivers run.

---

### Task 1: The tables

**Files:**
- Create: `src/Db/Migrator.php`, `migrations/001_identity.sql` (or per-dialect
  pair if the DDL cannot be written once — decide and say which)
- Test: `tests/Integration/Db/MigratorTest.php`

**What it builds.** The five tables of specification 9.1 — `ra_users`,
`ra_login_attempts`, `ra_password_resets`, `ra_activity_log`, `ra_migrations`
— and the runner that applies them.

**The runner's contract:** applies pending migrations in name order, records
each in `ra_migrations`, and is idempotent — running it twice applies nothing
the second time. It must be safe to run on every deploy, because that is how
it will actually be used.

**Corrected during execution: not "inside a transaction each".** MySQL's
implicit commit on DDL ends the transaction, so `inTransaction()` goes false
and the following `COMMIT` raises — a migration that applied correctly reports
failure. What keeps the ledger honest is ordering: the `ra_migrations` row is
written only after every statement in the file has succeeded. `Dialect::
transactionalDdl()` answers whether wrapping is safe, and PostgreSQL alone
says yes. See §11.2 of the design document.

**Corrected during execution: what the checksum actually guarantees.** Every
applied migration carries the hash of its file, and a run refuses when one no
longer matches. That protects an installation which has *already applied* the
file; one that has never applied it receives the edited version and applies it,
correctly. So the invariant is the rule — **an applied migration is never
edited** — and the checksum enforces it only where somebody already ran it. It
does not make the schema self-verifying, and reading it as if it does is how
people stop thinking about the case it leaves open.

**Write the DDL once if you can.** `BIGINT` and `VARCHAR` are portable;
`AUTO_INCREMENT` versus `GENERATED BY DEFAULT AS IDENTITY` is not, and
`Dialect` already knows that difference for writes. If one file cannot serve
both servers, say so in your report and put the difference in `Dialect` rather
than in a second copy of the whole schema.

- [ ] **Step 1: Write the failing test**

Cover: a fresh database gets all five tables; running twice is a no-op;
a partially applied set continues from where it stopped; a migration that
throws leaves no half-applied table and no row in `ra_migrations`. All on both
drivers.

---

### Task 2: Proving who somebody is

**Files:**
- Create: `src/Auth/Authenticator.php` (interface), `src/Auth/SqlAuthenticator.php`,
  `src/Auth/AuthUser.php`, `src/Auth/LoginResult.php`
- Test: `tests/Unit/Auth/SqlAuthenticatorTest.php`, `tests/Integration/Auth/LoginTest.php`

**Interfaces:**
```php
namespace RockAdmin\Auth;

interface Authenticator
{
    public function attempt(string $email, string $password, string $ip): LoginResult;
    public function byId(string $id): ?AuthUser;
    public function logout(AuthUser $user): void;
}
```

`AuthUser` carries id, email, name, roles and workspaces — and never the
password hash. A view object that can print a hash will eventually print one.

**What `SqlAuthenticator` does:** Argon2id via `password_hash()` /
`password_verify()`, rehashing when `password_needs_rehash()` says the cost
changed; records every attempt in `ra_login_attempts`; refuses when either the
email or the IP is over its threshold; updates `last_login_at` on success;
refuses an inactive user.

**Three things that must be true and are easy to get wrong:**

- **A wrong email and a wrong password must be indistinguishable** — same
  message, and the same work done. Returning early on an unknown email leaks
  which addresses exist, through timing. Verify against a dummy hash.
- **A refusal must not say which limit was hit.** "Too many attempts" is all
  the person gets.
- **`LoginResult` must distinguish "wrong" from "throttled" for the caller**
  without the message doing so, because the handler flashes one and logs the
  other.

- [ ] **Step 1: Write the failing test**

Per rule above, plus: a correct password succeeds; an inactive user is
refused; the tenth attempt from one IP is refused while the first from another
is not; a hash written at a lower cost is rehashed on a successful login and
the new hash verifies.

---

### Task 3: Identity in the kernel

**Files:**
- Create: `src/Auth/Identity.php`, `src/Http/UnauthorizedException.php`
- Modify: `src/Http/Kernel.php`
- Test: `tests/Unit/Auth/IdentityTest.php`, `tests/Unit/Http/KernelAuthTest.php`

**Steps 3 and 4 of the lifecycle.** `Identity` resolves the session's user id
through the `Authenticator`, and the kernel redirects an anonymous request to
`/login` — except for `/login` itself and `/_assets`, or nothing renders and
nothing styles the page it renders.

**A redirect, not a 403, and only for a document request.** A fragment
(`/r/...`) or an action (`/a/...`) arriving unauthenticated gets a 401,
because redirecting an AJAX call to a login page produces a grid full of login
form, which is how session expiry usually looks in admin tools.

**Session regeneration on login is not optional** — without it, a token fixed
before login is still valid after it.

- [ ] **Step 1: Write the failing test**

A document request with no session redirects to `/login`; `/login` itself does
not; `/_assets/...` does not; `/r/ads/grid` gets 401; `/a/ads/update` gets 401;
a request with a valid session reaches its handler; a session naming a user id
that no longer exists is treated as anonymous rather than crashing.

---

### Task 4: The login page

**Files:**
- Create: `src/Http/LoginHandler.php`, `src/Http/LogoutHandler.php`,
  `templates/page/login.php`
- Modify: `assets/css/rockadmin.css`

**The routes exist already** — `login.form`, `login.submit` and `logout` are
in `Router::ROUTES` and have been since milestone 1. This task registers
handlers for them; it does not touch the router.
- Test: `tests/Unit/Http/LoginHandlerTest.php`, `tests/Unit/Auth/LoginTemplatesTest.php`

**The form is an ordinary POST with a CSRF token**, like every other form in
this admin, and works with JavaScript disabled.

**What it must do:** GET draws the form; POST attempts and either redirects to
where the person was going or redraws with a message; a successful login
regenerates the session; logout forgets the session and redirects to `/login`.

**The return address is the one the milestone 7 rules already cover.** A
person deep-linked into `/p/ads?grid[page]=3` who is bounced to login should
land back there — through `ReturnAddress`, which already refuses anything that
leaves the admin. Do not write a second validator.

- [ ] **Step 1: Write the failing test**

Render the real template. Cover: the form has a CSRF token; a wrong password
redraws at 200 with a message and no session; a correct one redirects and sets
the session; the session id changes across a successful login; a return
address that points outside the admin is discarded; the password field is
`type="password"` and its value is never echoed back into the HTML.

---

### Task 5: What a role may do

**Files:**
- Create: `src/Auth/RoleSchema.php`, `src/Auth/Roles.php`, `src/Auth/Permission.php`
- Modify: `src/Config/Loader.php` (a `roles.php` alongside `enums.php`)
- Test: `tests/Unit/Auth/RolesTest.php`

**Roles are configuration, assignment is data** (9.2). This task is the
configuration half.

**Permission names are generated from the pages**, so a new page brings its
own: `{page}.view`, `.create`, `.update`, `.delete` — a page with no form
region generates no write permissions, which is the same fact that decides
whether its grid draws an edit link.

**`*` widens and `!` subtracts, and order does not matter.** `['ads.*',
'!ads.delete']` and `['!ads.delete', 'ads.*']` must resolve identically —
every subtraction applies after every widening, or the meaning of a role
depends on the order somebody happened to type it in.

- [ ] **Step 1: Write the failing test**

`['*']` allows everything; `['ads.*']` allows `ads.view` and not `users.view`;
`['*.view']` allows the view of every page and no write; the two orderings
above agree; an unknown permission is denied; a role naming a page that does
not exist is refused at load, naming it.

---

### Task 6: The gate

**Files:**
- Create: `src/Auth/Gate.php`
- Modify: `src/Http/Kernel.php`, `src/Http/Router.php` (a public accessor for
  the route names), every handler that needs a permission name
- Test: `tests/Unit/Auth/GateTest.php`, `tests/Integration/Auth/GateFlowTest.php`

**Step 7, for full pages, fragments and actions alike.** The check runs in the
kernel, before the handler, so it cannot be forgotten for an AJAX endpoint —
specification 5.2 calls that historically the most common hole in admin tools,
and this project's whole routing design exists to make it structural.

**A route must declare which permission it needs**, and a route that declares
none must be refused at registration rather than allowed through. The two
exceptions are `/login` and `/_assets`, and they are exceptions by name, in
one list, not by a flag each handler sets.

- [ ] **Step 1: Write the failing test**

The one that matters: **every registered route either names a permission or is
in the exception list** — assert it over the real route table, so a route
added later without a permission fails the suite rather than shipping open.
`Router::ROUTES` is private; add the accessor this needs.

Note that several declared routes have no handler yet — the four
`password.reset.*`, `workspace.switch`, `_setup`, `_diagnostics`. They are
still routes, so they still need an entry: an unhandled route must be
*declared* as requiring a permission or as an exception now, so that whoever
writes its handler inherits the decision instead of making it silently. Plus:
a user without `ads.view` gets 403 on `/p/ads`, on `/r/ads/grid` and on
`/a/ads/update`; a user with `ads.view` but not `ads.delete` may open the page
and not delete; 403 is a 403 and not a redirect.

---

### Task 7: Never draw what is forbidden

**Files:**
- Modify: `src/View/ViewFactory.php` or the shell builder, `src/Grid/ListRegion.php`,
  `src/Form/FormRegion.php`, the templates that draw buttons
- Test: `tests/Unit/Auth/HiddenWhenForbiddenTest.php`

**A button that returns 403 is a bug, not a safeguard** (9.2). A menu item for
a page the user may not view is not drawn; a create button without
`{page}.create` is not drawn; a row's edit link without `{page}.update` is not
drawn; a delete button without `{page}.delete` is not drawn.

**This is the same `Gate::allows()` the kernel calls.** If you find yourself
writing a second rule for what rendering may show, stop — that is how the two
drift until a button appears that the gate refuses.

**Milestone 7 left a matching trap here**, and it is worth reading before you
start: `createUrl` and the rows' edit links were decided by two different
conditions, so a read-only page drew Edit links into a 404 and its header had
one cell fewer than its body. One fact, read twice, is the rule.

- [ ] **Step 1: Write the failing test**

A viewer sees the page and no create button, no edit links and no actions
column at all; an editor sees them; the menu omits pages the user may not
view; and the header and body cell counts agree in both cases.

---

### Task 8: The record of what happened

**Files:**
- Create: `src/Auth/AuditLog.php`, `src/Auth/SqlAuditLog.php`
- Modify: `src/Db/SqlWriteHandler.php` or `src/Http/ActionHandler.php` — decide
  which and say why
- Test: `tests/Integration/Auth/AuditLogTest.php`

**Every write is recorded with a value diff** (9.4), defaulting to on.
`WriteResult` already carries `before`, `after` and `changed()`, which is what
this is for — do not compute a second diff.

**Where it hooks matters and the brief does not settle it.** Inside
`SqlWriteHandler` catches every write including future bulk actions, but gives
the data layer a dependency on identity. Inside `ActionHandler` keeps the
layering clean but records only writes that arrive by HTTP. Choose, implement,
and argue for it in your report — this is a real design decision and I would
rather read your reasoning than hand you mine.

**An audit row must not be able to fail the write it describes.** Decide
whether it joins the write's transaction or runs after it, and say what
happens when the log insert fails.

- [ ] **Step 1: Write the failing test**

A create, an update and a delete each write one row naming the user, the page,
the action, the row key and the diff; an update that changed nothing records
an empty diff rather than no row; the log records the user who did it, not the
row's owner.

---

### Task 9: Looking at it

**Files:** the demo — `demo/config/roles.php`, `demo/index.php`, `demo/seed.php`,
`demo/README.md`, `README.md`. Test: `tests/Integration/Auth/DemoAuthTest.php`.

**Seed three users** — a superadmin, an editor and a viewer — and wire the
login. Then **drive it in a browser** and report what was measured:

- logging in as each, and what each one can see
- the viewer sees no create button, no edit links, no actions column
- a forbidden URL typed by hand is a 403, including `/r/` and `/a/`
- logging out, and that the back button does not restore the admin
- a wrong password says the same thing as an unknown email
- the login page in light and dark, contrast measured from computed styles
- the whole flow with JavaScript disabled

Milestone 4 shipped an invisible toast, milestone 6 a grid with no links,
milestone 7 a toast covering the navigation, and the first real project found
a search placeholder that never rendered and a navbar that stood on end. Every
one of those passed its tests. This is the step that catches the next one.

---

## Milestone acceptance

1. `composer run check` green with both databases exported.
2. No runtime dependency beyond PHP extensions.
3. An anonymous request to any page, fragment or action is refused — page by
   redirect, fragment and action by 401.
4. Every registered route names a permission or is a named exception, asserted
   over the real registry.
5. A viewer cannot reach a write by URL, and is never shown a control for one.
6. Password hashing is Argon2id, and a hash at an old cost is upgraded on login.
7. Throttling survives a discarded session, and cannot be used to lock out a
   named account from a single address.
8. Every write leaves one audit row carrying its diff.
9. Every new configuration key is in the generated reference.

## What this milestone deliberately leaves out

- **Workspaces** (9.3) — the next milestone, for the reason at the top.
- **Password reset** (10) — needs `Mailer`.
- **OIDC** (9.5) — v1.1; the columns exist so it needs no migration.
- **Row-level and column-level permissions** — out of v1 by 13.
- **The CLI** (11) — `Migrator` is a class here; the command wraps it later.
