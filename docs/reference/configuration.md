# Configuration reference

<!-- Generated from the schema by `composer run docs:reference`. Do not edit by hand. -->

Every key `rockadmin.php` accepts. A key that is not listed here does not
exist: the loader refuses an unknown key rather than ignoring it, and suggests
the nearest declared one when it looks like a typo.

Values may carry placeholders. `{{env.NAME}}` and `{{config.path.to.key}}`
resolve once, while the configuration loads. `{{user.*}}` and
`{{workspace.*}}` survive as placeholders and bind per request, so they reach
the database as bound parameters and never as SQL text.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `url_mode` | string | `path` | `path` | How links are built: 'path' needs a rewrite rule, 'query' does not. |
| `debug` | bool | `false` | `true` | Shows what went wrong instead of a neutral error page. Never on in production. |
| `brand` | string | `RockAdmin` | `Cyklobazar admin` | Shown in the navbar. |
| `template_paths` | array | `[]` | `['resources/rockadmin', 'vendor/company/admin-theme']` | Directories searched for templates before the SDK's own, highest priority first. A file with the same name as an SDK template replaces it; nothing needs copying or registering. |

## `paths`

Writable directories, relative to the project root.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `logs` | string | — | `storage/logs/rockadmin` | Errors, mail and the audit trail. Must be outside the document root. **Required.** |
| `cache` | string | — | `storage/cache/rockadmin` | Where the compiled configuration is written. It can hold resolved {{env.*}} values, so it must be outside the document root. *Performance:* Without it the configuration is read and validated on every request. |

## `mail`

How the admin sends a password reset.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `driver` | string | `log` | `smtp` | log, smtp, sendmail or callback. |
| `host` | string or null | — | `{{env.MAIL_HOST}}` | SMTP host. Null while the driver does not need one. |
| `port` | int | `587` | `587` | SMTP port. |

## `assets`

Project CSS and JS, loaded after the SDK’s so they override it.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `css` | array | `[]` | `['/css/admin.css']` | Stylesheet URLs a project adds on top of the SDK’s own, for branding or overriding the default look. |
| `js` | array | `[]` | `['/js/admin.js']` | Script URLs a project adds on top of the SDK’s own, for custom widgets or page behaviour. |

## `theme`

The default look. Replace it wholesale with a stylesheet in assets.css.

| Key | Type | Default | Example | What it does |
| --- | --- | --- | --- | --- |
| `dark` | string | `auto` | `auto` | Dark mode: 'auto' follows the operating system, 'on' and 'off' decide. |
