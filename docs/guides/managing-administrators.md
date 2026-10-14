# Administrators, mail and password reset

Everything about who may sign in to the admin, and the two messages it sends.

## Getting in the first time

A freshly migrated installation has no administrators, and there are two ways
to make the first one:

```bash
vendor/bin/rockadmin migrate     # creates the ra_* tables
```

- **`/_setup`** — a form that creates one superadmin and signs you in. It
  exists *only* while `ra_users` is empty and answers 404 the moment it is not;
  the account it creates carries `founder`, a column a unique index permits one
  row to hold, so two people doing this at the same moment end with one
  administrator rather than two.
- **the CLI** — `vendor/bin/rockadmin user:create`, when the CLI milestone
  ships. A host with shell access and a host with only FTP and a browser are
  different hosts, so both ways stay.

Nothing else in the admin is reachable until that account exists: every page
redirects to the login form, and the login form has nobody to let in.

## The administrators page

Administrators are managed as an ordinary page over `ra_users` — a grid, a
form, an action, the same keys any other page uses. Copy
[`demo/config/pages/administrators.php`](../../demo/config/pages/administrators.php)
into your own `pages/` and change the two option lists to match your
`roles.php` and `workspaces.php`.

The part a page file cannot express ships with the SDK:

```php
use RockAdmin\Auth\AdminUserOperation;

$operations = Operations::of([
    'ra_admin_user' => new AdminUserOperation(
        new AdminUsers($connection),
        $roles,                      // Roles::fromConfig(...)
        new PasswordResets($connection, $inviteLifetimeMinutes),
        new MessageFactory($renderer, Mailers::from($config), $brand),
        Mailers::fromConfig($config),
        $urls,
        $publicUrl,                  // from `public_url`
        $inviteLifetimeMinutes,      // from `auth.invite_lifetime_minutes`
    ),
]);
```

It is the SDK using its own seam for its own table (see
[Your own write logic](own-write-logic.md)), and it does five things a plain
form save gets wrong:

| | |
|---|---|
| **An empty password box changes nothing** | An edit form cannot show the existing password, so the box is always empty; writing it through would clear the password of everybody edited for any other reason |
| **`roles` and `workspaces` are JSON** | A multiselect produces a list, and `WriteHandler` binds scalars |
| **The last way in cannot be removed** | Not by deactivating, demoting or deleting the last account that holds the widest role, and not by deleting the account you are signed in with |
| **Nobody hands out more than they hold** | Somebody who may edit administrators cannot grant a role or a workspace they do not have themselves — otherwise that one permission is the permission to award every other |
| **No password means an invitation** | A set-password link, sent after the commit, because a transaction can take back a row and cannot take back an e-mail |

Give the page's permissions to the role that should have them, and take them
off the others explicitly — `*.view` includes `administrators.view`, which is a
list of every address that can sign in:

```php
'viewer' => ['permissions' => ['*.view', '!administrators.*']],
```

## Mail

```php
'mail' => [
    'driver' => 'log',               // log | smtp | sendmail | callback
    'from' => ['address' => '{{env.MAIL_FROM}}', 'name' => 'Cyklobazar admin'],
],
'public_url' => 'https://admin.cyklobazar.cz',
```

**`log` is the default and is a development tool, not a placeholder.** It
writes each message to `logs/mail/` as a complete `.eml` that a mail client
opens, so a password reset works end to end with no mail server: the link is in
the file.

**`callback` is what a real project should use.** A host running Laravel or
Symfony already has a mailer, its templates, its queue, its retries and its
deliverability warmed up:

```php
Mailers::fromConfig($config, fn (Message $message) => Mail::send(/* … */));
```

The closure goes to the bootstrap and not into `rockadmin.php`, because a
closure cannot be written into the configuration cache — and the cache is what
makes production a single `include`.

`smtp` is a socket client with STARTTLS and AUTH for hosts that have neither,
and `sendmail` is `mail()` for shared hosting that has a local MTA and nothing
else. Both work; neither knows whether the message arrived.

**`public_url` is required for any mail to be sent**, and it is deliberately
not derived from the request. A link built from the `Host` header is a link an
attacker chooses by sending one: the mail goes to the right person and the link
points at the wrong server. With it empty, the reset form says so plainly and
sends nothing.

### Changing what a message says

`templates/mail/` goes through the same cascade as every other template, so
`mail/password-reset.php` (and `mail/password-reset.txt.php`) in your own
template directory replaces the SDK's. Both halves are rendered and both are
sent; a message with only one is not something this SDK can produce.

## Password reset

Four routes, and nothing to wire beyond the handler:

| Route | What it is |
|---|---|
| `GET /password/reset` | the form that asks for an address |
| `POST /password/reset` | issues a link, or says exactly the same thing if the address is unknown |
| `GET /password/reset/{token}` | the form behind a live link |
| `POST /password/reset/{token}` | sets the password and spends the link |

What it promises:

- **a link works once**, expires after `auth.reset_lifetime_minutes`, and is
  not in the database — the column holds its SHA-256, so a leaked backup is not
  a set of working links;
- **the answer never says whether an address exists**, including when the
  mailer is broken;
- **setting a password does not sign anybody in**, because a mailbox is not a
  credential with a throttle behind it. It does clear that address's login
  lockout, so somebody who reset their password *because* they were locked out
  is not still locked out;
- **asking too often is refused** under its own throttle, separate from the
  login one: ten forgotten passwords must not lock the login form, and a login
  lockout must not block the one self-service way out of itself.

What it does **not** promise: sessions already open survive a password change.
"Logout everywhere" needs a `sessions_valid_from` column and a `SessionStore`
that records when a session began, and specification 9.1 is explicit that
stamping a column nothing reads would look exactly like the feature. It is
v1.1.

## The numbers

| Key | Default | What it decides |
|---|---|---|
| `auth.attempts_per_email` | 10 | sign-ins one address may try per window |
| `auth.attempts_per_ip` | 20 | sign-ins one machine may try, counting every address |
| `auth.window_minutes` | 15 | how long both are counted over, and how long a lockout lasts |
| `auth.reset_lifetime_minutes` | 60 | how long a reset link works |
| `auth.invite_lifetime_minutes` | 2880 | how long an invitation works — longer, because nobody asked for it and it waits for somebody to read their mail |

Each is in [the generated reference](../reference/configuration.md) with the
reasoning behind its default.
