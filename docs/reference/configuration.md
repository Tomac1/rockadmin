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
| `public_url` | string | `` | `https://admin.cyklobazar.cz` | Where this admin is reachable from outside, as an absolute URL with no trailing slash. It is used for the links in mail, and only there -- every page of the admin itself builds relative URLs and needs none of this.\n\nIt is configuration rather than something read from the request on purpose. A link built from the Host header is a link an attacker can choose by sending one, which turns a password reset into a way of collecting reset tokens: the mail goes to the right person and the link points at the wrong server. Left empty, a reset link cannot be built and the admin says so rather than sending a message nobody can use. |
| `pages_path` | string | `pages` | `pages` | Where page files live, relative to the configuration directory. One file per page, named after the page: pages/ads.php is reachable at /p/ads. |
| `per_page` | int | `25` | `50` | Rows in one page of a grid, for regions that do not set their own. *Performance:* A large value makes every grid render slower for everyone; set it per region where a particular page needs more. |

## `paths`

Writable directories, relative to the project root.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `logs` | string | — | `storage/logs/rockadmin` | Errors, mail and the audit trail. Must be outside the document root. **Required.** |
| `cache` | string | — | `storage/cache/rockadmin` | Where the compiled configuration is written. It can hold resolved {{env.*}} values, so it must be outside the document root. *Performance:* Without it the configuration is read and validated on every request. |

## `auth`

How hard it is to guess a password, and how long a reset link lasts. The defaults are deliberately survivable: an administrator who has genuinely forgotten their password tries a handful of variants, and a lockout they cannot sit out is a support ticket.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `attempts_per_email` | int | `10` | `5` | Sign-in attempts one address may make inside the window before the next is refused. Ten leaves room for somebody trying the variants they might have used, and is nowhere near enough to guess anything an Argon2id hash is protecting. |
| `attempts_per_ip` | int | `20` | `40` | Attempts one machine may make, counting every address it tried. Larger than the per-address allowance on purpose: one office address carries every administrator behind it, and one confused colleague must not lock out the rest. |
| `window_minutes` | int | `15` | `30` | How long both allowances are counted over, and therefore how long somebody waits after exhausting one. Fifteen minutes is a wait that can be sat out; an hour is one that gets reported as an outage. |
| `reset_lifetime_minutes` | int | `60` | `30` | How long a password-reset link works for, from specification 10. It is single-use whatever this says -- the lifetime is the second limit, for a link that was never used and is sitting in a mailbox. |
| `invite_lifetime_minutes` | int | `2880` | `10080` | How long an invitation link works for. Longer than a reset, because nobody asked for it and it waits for somebody to read their mail: two days covers a weekend, and an invitation that expired unseen is a second message somebody has to send by hand. |

## `storage`

Where uploaded files are kept. The default driver writes them to a directory and the admin serves them, which needs nothing configured but a path; the s3 driver talks to Cloudflare R2 or anything else speaking the same API, with no library installed.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `driver` | string | `local` | `s3` | local or s3. A project that already has its own disk abstraction -- Laravel Storage, Flysystem -- wires a Storage object in the bootstrap instead and neither of these runs. |
| `path` | string | `` | `storage/uploads` | For the local driver: the directory files live in, relative to the project root. **It must be outside the document root.** A directory of uploads inside it is a directory a stranger can fetch by guessing, and on a server that runs PHP by file extension rather than by location, one they may be able to execute. |
| `public_url` | string | `` | `https://cdn.cyklobazar.cz/uploads` | Serve files from this address instead of through the admin, as an absolute URL with no trailing slash. Empty -- the default -- means every file goes through a route only a signed-in administrator can use. Setting it is a decision that the files behind it are public to anybody who learns the address, which is right for product photos and wrong for scanned invoices. |
| `bucket` | string or null | — | `cyklobazar-uploads` | For the s3 driver: the bucket name. |
| `endpoint` | string or null | — | `https://{{env.R2_ACCOUNT}}.r2.cloudflarestorage.com` | For the s3 driver: the API endpoint, with scheme and no path. R2 gives you one per account, and it is not the public bucket address -- that one goes in public_url. |
| `region` | string | `auto` | `eu-central-1` | The region a signature is computed for. R2 uses 'auto' and so does this; an S3 bucket needs its own, and getting it wrong fails exactly like a wrong password because the server will not say which. |
| `key` | string or null | — | `{{env.R2_ACCESS_KEY_ID}}` | For the s3 driver: the access key id. |
| `secret` | string or null | — | `{{env.R2_SECRET_ACCESS_KEY}}` | For the s3 driver: the secret. Write it as {{env.*}} and keep it out of git -- this file is committed, and the configuration cache is written with the value resolved, which is why that cache lives outside the document root. |
| `link_minutes` | int | `10` | `5` | How long a signed link to a private file stays good for. Long enough to open a page and look at it, short enough that a URL copied out of a browser history is no use tomorrow. |

## `uploads`

What a file field accepts. A field may narrow both of these and may not widen them: whatever a page file says, nothing larger or stranger than this is written anywhere.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `max_bytes` | int | `5242880` | `2097152` | The largest file accepted, in bytes. Five megabytes by default, which is a photograph. PHP's own upload_max_filesize and post_max_size cap this from below whatever it says, and a request over that limit arrives with its whole body discarded -- which the admin notices and reports as a size problem rather than as a failed form. |
| `types` | array | `['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf']` | `['image/jpeg', 'image/png']` | The content types accepted anywhere in this admin, as an allowlist. The type is read from the bytes rather than from what the browser claimed, and the stored file gets the extension this admin maps that type to -- so an upload called invoice.php.jpg is stored as .jpg whatever is inside it. |

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
