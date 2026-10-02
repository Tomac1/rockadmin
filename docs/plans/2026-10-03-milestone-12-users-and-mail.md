# Milestone 12 — Administrators, and mail

Specification section 10 in full, and the half of 9.1 that is reachable from a
browser: an admin that can send a message, let somebody who has forgotten
their password back in, create its first administrator, and manage the rest.

Four routes answer 404 today — `password/reset` twice and
`password/reset/{token}` twice — and so do `_setup`'s two. `Gate` already
marks all six anonymous, `Router` already matches them, `ra_password_resets`
already exists in migration 001. The schema was built for this milestone; it
is the handlers, the mailer and the pages that are missing.

## Decisions this plan makes

**Mail is a seam before it is a feature.** Rule 2 lists `Mailer` beside
`SessionStore` and `Authenticator`: a project running Laravel already has a
mailer, already has its templates, its queue and its deliverability set up,
and the admin has no business opening a second socket to do worse. So the
interface comes first and the four drivers are implementations of it — the
`callback` driver, which is three lines, is the one most real projects will
use, and the other three exist so that a framework-less project is not stuck.

**Both halves of every message, always.** Specification 10 says HTML and plain
text. Generating the text by stripping tags from the HTML is the usual
shortcut and it produces the kind of plain text nobody reads — a reset link
wrapped in navigation. Two templates per message, through the same cascade a
project overrides anything else with.

**The SMTP client is written against a socket seam, not against `fsockopen`.**
Two hundred lines of protocol with no test is two hundred lines nobody can
change. The transport is an interface with a stream implementation and a
scripted fake, so EHLO, STARTTLS, AUTH, dot-stuffing and the failure paths are
testable without a server; a test against a real server runs only when
`RA_TEST_SMTP_*` is set, the way the database tests do.

**A reset token is a secret the database must not hold.** `ra_password_resets`
stores `token_hash`, which is what the column is called, and the token itself
exists only in the mail. A database that leaks must not hand over working
reset links. Single use (`used_at`), 60 minutes, and the row is claimed
transactionally so two clicks on the same link cannot both win.

**The answer is the same whether the address exists or not.** "If that address
belongs to an administrator, we have sent a link." Anything else turns the
form into a list of who works here. The mail is sent after the response is
decided, not before, so the two paths do the same work in the same order.

**Resetting a password does not sign anybody in.** The person lands on the
login page with a flash saying it worked. A reset link that signs you in makes
the mailbox a second credential with no throttle, no attempt record and no
"wrong password" counter behind it; one extra form is a small price for
keeping login the only door. (It is also the behaviour that survives the link
being forwarded, which happens.)

**Throttling is one thing, with a kind.** `ra_login_attempts` exists and
`SqlAuthenticator` counts rows in it inline. Reset requests need the same
counting, by email and by address, and must not share a counter with login —
ten forgotten passwords must not lock out the login form, and vice versa.
So the counting comes out of `SqlAuthenticator` into a `Throttle` of its own,
and migration 003 adds `kind` to the table. A second table would have been the
other option; one table with a kind keeps "how many attempts has this address
made at anything" answerable, which is the question an incident asks.

**The throttle's numbers become configuration.** `SqlAuthenticator`'s docblock
already says so: its three constructor numbers are there "until that section
is written", and this is that section. `auth.attempts_per_email`,
`auth.attempts_per_ip`, `auth.window_minutes`, `auth.reset_lifetime_minutes`,
`auth.invite_lifetime_hours`. Rule 5 means they arrive with descriptions and
examples and in the generated reference.

**`_setup` exists only while there is nobody.** The first administrator has to
come from somewhere, and specification 11.2's answer is the CLI — which is a
later milestone. Until then `_setup` is the web answer, and it is 404 the
moment `ra_users` holds anybody at all. Not 403: a 403 tells whoever found the
URL that the installation is live and somebody is already in it.

**Administrators are managed as an ordinary page**, described in
configuration like any other, over `ra_users`. This is the decision most worth
arguing with, and the alternative — the SDK shipping a built-in page of its
own — needs machinery that does not exist: a second page root in
`PageRepository`, a rule for how a project overrides a built-in page, an
answer for workspaces listing `'@all'`, and a permission namespace nobody
declared. A page file in configuration needs none of that and is editable by
the project that owns the admin, which is the point of the project.

What the SDK ships instead is the part a page file cannot express: an
`Operation` that hashes the password, writes `roles` and `workspaces` as JSON,
refuses the last superadmin's own deactivation, and sends the invitation. The
page file is a scaffold the documentation carries and the demo runs, and
`make:page` will write it when the CLI milestone arrives.

**An invitation is a reset with a longer fuse.** Creating an administrator
with no password and mailing them a link reuses the token table, the handler
and the template — a second "set your password" mechanism would be a second
thing to get wrong. Sixty minutes is wrong for an invitation that waits for
somebody to read their mail, so invitations carry their own lifetime in hours.

**No JSON cast in the write path.** `roles` and `workspaces` are JSON columns
and a multiselect produces a list, which `SqlWriteHandler` cannot bind. The
general fix — a column cast system — is a design decision for the data layer
and would arrive here as a side effect of needing it once. The shipped
operation encodes those two columns itself, and the plan says so out loud so
the next page that needs it knows this is not a precedent.

## Pre-flight scan

**Nothing exists under `src/Mail`.** The directory in CLAUDE.md's layout is
aspirational; this milestone creates it.

**The `mail` schema block is a stub.** `RootSchema` has `driver`, `host` and
`port`. Specification 10 also has `from.address`, `from.name`, `encryption`,
`username` and `password`. The missing keys are a rule-5 failure waiting to
happen the moment anything reads them.

**`SqlAuthenticator` holds the throttle inline:** `isThrottled()`, `record()`,
`normaliseEmail()`, `normaliseIp()`, and a `DELETE FROM ra_login_attempts` on
success. All four move; the delete stays, keyed by kind.

**`LoginHandler` is the shape the reset handlers copy** — anonymous, CSRF
checked before the body is read, redraws its own form with a message rather
than redirecting, and renders through `templates/page/login.php` with a
`LoginView`. Two more views and two more templates, same shell.

**`Migrator` refuses an edited migration**, so the `kind` column is migration
003 and not an edit to 001.

**The demo has three seeded administrators and a `logs/` path configured**,
so the log driver has somewhere to write and the demo can show a reset
end to end without a mail server.

## Global constraints

- **No runtime dependencies.** The SMTP client is sockets; the mail templates
  are PHP; nothing is added to `composer.json`.
- **No framework coupling.** `Mailer` is the seam; `CallbackMailer` is how a
  host hands over its own.
- **Every configuration key is in the schema**, or `ReferenceIsCurrentTest`
  fails.
- **No native browser dialogs** (rule 6) — the administrators page confirms
  through `templates/ui/confirm.php` like everything else.
- **Both drivers**, and the reset flow is SQL: `ra_password_resets` is written
  and claimed on MySQL and PostgreSQL alike.

---

### Task 1: What a message is, and the log driver

**Files:** create `src/Mail/Mailer.php`, `Message.php`, `Mailbox.php`,
`LogMailer.php`, `MailException.php`; modify `src/Config/RootSchema.php`.
Test: `tests/Unit/Mail/MessageTest.php`, `tests/Unit/Mail/LogMailerTest.php`.

`Message` carries from, to, subject, and both bodies. `LogMailer` writes one
`.eml` per message under `logs/mail/`, openable in a mail client — which is
what makes the default driver a usable development tool rather than a
placeholder.

**The test that matters:** the written file is a well-formed MIME multipart
with both parts, and a subject with a Czech word in it survives as encoded
words rather than as mojibake.

---

### Task 2: The templates, and what builds a message

**Files:** create `templates/mail/layout.php`, `layout.txt.php`,
`password-reset.php`, `password-reset.txt.php`, `invitation.php`,
`invitation.txt.php`; `src/Mail/MessageFactory.php`.
Test: `tests/Unit/Mail/MessageFactoryTest.php`.

Rendered through the existing `Renderer` and `TemplateResolver`, so a project
overrides a mail template exactly as it overrides a form field.

**The test that matters:** both halves carry the link, and the HTML half
escapes what the text half does not need to.

---

### Task 3: The other three drivers

**Files:** create `src/Mail/SendmailMailer.php`, `CallbackMailer.php`,
`SmtpMailer.php`, `SmtpTransport.php`, `StreamTransport.php`,
`Mailers.php` (the factory reading configuration).
Test: `tests/Unit/Mail/SmtpMailerTest.php`, `tests/Unit/Mail/MailersTest.php`.

**The test that matters:** a scripted conversation — EHLO, STARTTLS, AUTH
LOGIN, MAIL FROM, RCPT TO, DATA, a line beginning with a dot, QUIT — and a
refusal at each step that fails loudly rather than reporting success.

---

### Task 4: One throttle, with a kind

**Files:** create `src/Auth/Throttle.php`, `migrations/003_throttle_kind.sql`;
modify `src/Auth/SqlAuthenticator.php`, `src/Config/RootSchema.php`.
Test: `tests/Integration/Auth/ThrottleTest.php`.

The counting comes out of `SqlAuthenticator` unchanged in behaviour, gains a
`kind`, and the numbers become `auth.*` configuration keys.

**The test that matters:** reset requests cannot exhaust the login allowance,
and a login lockout does not stop somebody asking for a reset — which is the
one thing a shared counter would get wrong, and the one that locks an
administrator out of their own admin for a quarter of an hour.

---

### Task 5: Tokens

**Files:** create `src/Auth/PasswordResets.php`, `PasswordReset.php`.
Test: `tests/Integration/Auth/PasswordResetsTest.php`.

Issue, claim, complete. The token is 32 random bytes; the database holds its
SHA-256. Claiming is a single `UPDATE … SET used_at = ? WHERE token_hash = ?
AND used_at IS NULL` so that two clicks race in the database rather than in
PHP.

**The test that matters:** a used token, an expired token and an unknown token
are all the same answer; and the same token claimed twice succeeds once.

---

### Task 6: The four routes

**Files:** create `src/Http/PasswordResetHandler.php`,
`src/View/PasswordResetView.php`, `templates/page/password.php`.
Test: `tests/Integration/Http/PasswordResetFlowTest.php`.

Ask, receive, set, sign in. The response to the request form is constant; the
set form is drawn only for a token that is still good, and refuses a password
the same validator refuses anywhere else.

**The test that matters:** the whole flow end to end against a real database
and a capturing mailer — request, read the link out of the message, follow it,
set a password, and sign in with it. Plus: the old password no longer works,
and the link does not work twice.

---

### Task 7: The first administrator

**Files:** create `src/Http/SetupHandler.php`, `src/Auth/AdminUsers.php`,
`templates/page/setup.php`.
Test: `tests/Integration/Http/SetupTest.php`.

`_setup` draws a form while `ra_users` is empty and 404s the moment it is not.
It creates one superadmin and signs them in.

**The test that matters:** the second visit is a 404, and a POST to it with a
valid token is a 404 too — the check is on the write, not only on the form.

---

### Task 8: Managing administrators

**Files:** create `src/Auth/AdminUserOperation.php`;
`demo/config/pages/administrators.php`; `docs/guides/managing-administrators.md`;
modify `demo/index.php`, `demo/seed.php`.
Test: `tests/Integration/Auth/AdminUserOperationTest.php`.

The page is configuration; the operation is what configuration cannot say.
Hashing on create and on change, never writing an empty password over a real
one; `roles` and `workspaces` encoded as JSON; an invitation sent after the
commit; and the refusal that keeps an installation reachable — you may not
deactivate or demote the last account holding `*`.

**The test that matters:** saving the form with the password box left empty
leaves the stored hash alone, and the last superadmin cannot lock the
building.

---

### Task 9: Looking at it

**Files:** `docs/guides/`, the plan's own report.
Test: a browser pass.

Forgotten password end to end in the demo with the log driver, the `.eml` file
opened, the link followed, the new password used. The administrators page
creating somebody, inviting them, and refusing the last superadmin. Both at
desktop width; narrow viewports are still the interlude this milestone does
not take.

## Milestone acceptance

1. `composer run check` green with both databases.
2. A project can replace the mailer with its own in one constructor argument.
3. Every message goes out in HTML and plain text, through overridable
   templates.
4. A forgotten password can be recovered without a database client, and the
   form says the same thing whether the address exists or not.
5. A reset link works once, expires in an hour, and is useless to somebody
   holding the database.
6. An installation with no administrators can create its first one, and one
   with administrators cannot.
7. Administrators are created, edited, invited and deactivated from inside the
   admin, and the last superadmin cannot remove their own access.
8. Every new key is in the generated reference.

## What this milestone deliberately leaves out

- **The CLI.** `user:create`, `migrate` and `doctor` are specification 11 and
  a milestone of their own; `_setup` is the web answer until then.
- **OIDC, two-factor, "logout everywhere".** 9.5 defers them, and 9.1 says
  exactly what the third needs before it can be built honestly.
- **A JSON column cast.** The shipped operation encodes two columns; a general
  cast is a data-layer decision and not a side effect of needing it once.
- **Queued or retried mail.** A failed send raises, the request says so, and a
  host wanting a queue has `CallbackMailer`.
- **Built-in pages.** Discussed above: it needs a second page root and an
  override rule, and a page file costs nothing.
