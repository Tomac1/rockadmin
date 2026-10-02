# Configuration reference

<!-- Generated from the schema by `composer run docs:reference`. Do not edit by hand. -->

Every key `rockadmin.php` accepts. A key that is not listed here does not
exist: the loader refuses an unknown key rather than ignoring it, and suggests
the nearest declared one when it looks like a typo.

Values may carry placeholders. `{{env.NAME}}` and `{{config.path.to.key}}`
resolve once, while the configuration loads. `{{user.*}}` and
`{{workspace.*}}` survive as placeholders and bind per request, so they reach
the database as bound parameters and never as SQL text.

The pages themselves are described in their own files and documented in
[the page reference](pages.md).

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `url_mode` | string | `path` | `path` | How links are built: 'path' needs a rewrite rule, 'query' does not. |
| `debug` | bool | `false` | `true` | Shows what went wrong instead of a neutral error page. Never on in production. |
| `brand` | string | `RockAdmin` | `Cyklobazar admin` | Shown in the navbar. |
| `template_paths` | array | `[]` | `['resources/rockadmin', 'vendor/company/admin-theme']` | Directories searched for templates before the SDK's own, highest priority first. A file with the same name as an SDK template replaces it; nothing needs copying or registering. |
| `pages_path` | string | `pages` | `pages` | Where page files live, relative to the configuration directory. One file per page, named after the page: pages/ads.php is reachable at /p/ads. |
| `per_page` | int | `25` | `50` | Rows in one page of a grid, for regions that do not set their own. *Performance:* A large value makes every grid render slower for everyone; set it per region where a particular page needs more. |

## `paths`

Writable directories, relative to the project root.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `logs` | string | — | `storage/logs/rockadmin` | Errors, mail and the audit trail. Must be outside the document root. **Required.** |
| `cache` | string | — | `storage/cache/rockadmin` | Where the compiled configuration is written. It can hold resolved {{env.*}} values, so it must be outside the document root. *Performance:* Without it the configuration is read and validated on every request. |

## `mail`

How the admin sends a password reset or an invitation. The default driver writes each message to a file instead of sending it, so an installation with no mail server still has a working password reset: the link is in the file.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `driver` | string | `log` | `smtp` | log, smtp, sendmail or callback. The log driver writes one .eml per message under logs/mail and sends nothing. The callback driver is the one a project with its own mailer wants: the closure is passed to the bootstrap, because configuration holds data and the host supplies services. |
| `host` | string or null | — | `{{env.MAIL_HOST}}` | SMTP host. Null while the driver does not need one. |
| `port` | int | `587` | `587` | SMTP port. |
| `encryption` | string | `tls` | `ssl` | How the SMTP connection is secured: 'tls' issues STARTTLS on port 587, 'ssl' opens the socket encrypted on port 465, and 'none' sends in the clear -- which means the password below crosses the network as text, so it is for a server on the same machine and nothing else. |
| `username` | string or null | — | `{{env.MAIL_USERNAME}}` | SMTP username. Null for a server that does not ask. |
| `password` | string or null | — | `{{env.MAIL_PASSWORD}}` | SMTP password. Write it as {{env.MAIL_PASSWORD}} and keep it out of git -- this file is committed, and the configuration cache is written with the value resolved, which is why that cache lives outside the document root. |
| `timeout` | int | `10` | `5` | Seconds to wait for the SMTP server, on connecting and on every reply. A request that sends mail waits for this, so it is short on purpose: a mail server that has stopped answering must not hold a browser open. |

## `mail.from`

Who the mail is from. Required for every driver except callback, where the project already knows.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `address` | string | `` | `{{env.MAIL_FROM}}` | The sender address. An address at a domain this server is allowed to send for, or the mail will be filed as spam whatever the driver does. |
| `name` | string | `` | `Cyklobazar admin` | The name shown beside the address. |

## `assets`

Project CSS and JS, loaded after the SDK’s so they override it.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `css` | array | `[]` | `['/css/admin.css']` | Stylesheet URLs a project adds on top of the SDK’s own, for branding or overriding the default look. |
| `js` | array | `[]` | `['/js/admin.js']` | Script URLs a project adds on top of the SDK’s own, for custom widgets or page behaviour. |

## `audit`

The record of what happened: one row in ra_activity_log per write.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `enabled` | bool | `true` | `false` | Records every create, update and delete with who did it and a diff of what changed. On by default, because history that was not collected cannot be recovered afterwards; turn it off only where another system already keeps the same record. |

## `theme`

The default look. Replace it wholesale with a stylesheet in assets.css.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `dark` | string | `auto` | `auto` | Dark mode: 'auto' follows the operating system, 'on' and 'off' decide. Writes data-ra-theme on the document; the bundled stylesheets define no dark palette yet, so today this is a hook for a project's own CSS rather than a switch. |
