<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * The schema for rockadmin.php.
 *
 * Page, column, field and filter schemas arrive with the regions that give
 * them meaning, in later milestones. This is the file the loader reads today.
 */
final class RootSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'url_mode' => new SchemaKey(
                ValueType::String,
                default: 'path',
                description: "How links are built: 'path' needs a rewrite rule, 'query' does not.",
                example: 'path',
            ),
            'debug' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Shows what went wrong instead of a neutral error page. Never on in production.',
                example: true,
            ),
            'brand' => new SchemaKey(
                ValueType::String,
                default: 'RockAdmin',
                description: 'Shown in the navbar.',
                example: 'Cyklobazar admin',
            ),
            'paths' => new SchemaKey(
                ValueType::Array,
                description: 'Writable directories, relative to the project root.',
                children: new Schema([
                    'logs' => new SchemaKey(
                        ValueType::String,
                        required: true,
                        description: 'Errors, mail and the audit trail. Must be outside the document root.',
                        example: 'storage/logs/rockadmin',
                    ),
                    'cache' => new SchemaKey(
                        ValueType::String,
                        description: 'Where the compiled configuration is written. It can hold '
                            . 'resolved {{env.*}} values, so it must be outside the document root.',
                        example: 'storage/cache/rockadmin',
                        performance: 'Without it the configuration is read and validated on every request.',
                    ),
                ]),
            ),
            'template_paths' => new SchemaKey(
                ValueType::Array,
                default: [],
                description: 'Directories searched for templates before the SDK\'s own, highest priority '
                    . 'first. A file with the same name as an SDK template replaces it; nothing needs '
                    . 'copying or registering.',
                example: ['resources/rockadmin', 'vendor/company/admin-theme'],
            ),
            'public_url' => new SchemaKey(
                ValueType::String,
                default: '',
                description: 'Where this admin is reachable from outside, as an absolute URL with no '
                    . 'trailing slash. It is used for the links in mail, and only there -- every page '
                    . 'of the admin itself builds relative URLs and needs none of this.\n\n'
                    . 'It is configuration rather than something read from the request on purpose. A '
                    . 'link built from the Host header is a link an attacker can choose by sending '
                    . 'one, which turns a password reset into a way of collecting reset tokens: the '
                    . 'mail goes to the right person and the link points at the wrong server. Left '
                    . 'empty, a reset link cannot be built and the admin says so rather than sending '
                    . 'a message nobody can use.',
                example: 'https://admin.cyklobazar.cz',
            ),
            'auth' => new SchemaKey(
                ValueType::Array,
                description: 'How hard it is to guess a password, and how long a reset link lasts. '
                    . 'The defaults are deliberately survivable: an administrator who has genuinely '
                    . 'forgotten their password tries a handful of variants, and a lockout they '
                    . 'cannot sit out is a support ticket.',
                children: new Schema([
                    'attempts_per_email' => new SchemaKey(
                        ValueType::Int,
                        default: 10,
                        description: 'Sign-in attempts one address may make inside the window before '
                            . 'the next is refused. Ten leaves room for somebody trying the variants '
                            . 'they might have used, and is nowhere near enough to guess anything an '
                            . 'Argon2id hash is protecting.',
                        example: 5,
                    ),
                    'attempts_per_ip' => new SchemaKey(
                        ValueType::Int,
                        default: 20,
                        description: 'Attempts one machine may make, counting every address it tried. '
                            . 'Larger than the per-address allowance on purpose: one office address '
                            . 'carries every administrator behind it, and one confused colleague must '
                            . 'not lock out the rest.',
                        example: 40,
                    ),
                    'window_minutes' => new SchemaKey(
                        ValueType::Int,
                        default: 15,
                        description: 'How long both allowances are counted over, and therefore how '
                            . 'long somebody waits after exhausting one. Fifteen minutes is a wait '
                            . 'that can be sat out; an hour is one that gets reported as an outage.',
                        example: 30,
                    ),
                    'reset_lifetime_minutes' => new SchemaKey(
                        ValueType::Int,
                        default: 60,
                        description: 'How long a password-reset link works for, from specification 10. '
                            . 'It is single-use whatever this says -- the lifetime is the second '
                            . 'limit, for a link that was never used and is sitting in a mailbox.',
                        example: 30,
                    ),
                    'invite_lifetime_minutes' => new SchemaKey(
                        ValueType::Int,
                        default: 2880,
                        description: 'How long an invitation link works for. Longer than a reset, '
                            . 'because nobody asked for it and it waits for somebody to read their '
                            . 'mail: two days covers a weekend, and an invitation that expired '
                            . 'unseen is a second message somebody has to send by hand.',
                        example: 10080,
                    ),
                ]),
            ),
            'mail' => new SchemaKey(
                ValueType::Array,
                description: 'How the admin sends a password reset or an invitation. The default '
                    . 'driver writes each message to a file instead of sending it, so an '
                    . 'installation with no mail server still has a working password reset: the '
                    . 'link is in the file.',
                children: new Schema([
                    'driver' => new SchemaKey(
                        ValueType::String,
                        default: 'log',
                        description: 'log, smtp, sendmail or callback. The log driver writes one .eml '
                            . 'per message under logs/mail and sends nothing. The callback driver is '
                            . 'the one a project with its own mailer wants: the closure is passed to '
                            . 'the bootstrap, because configuration holds data and the host supplies '
                            . 'services.',
                        example: 'smtp',
                    ),
                    'from' => new SchemaKey(
                        ValueType::Array,
                        description: 'Who the mail is from. Required for every driver except callback, '
                            . 'where the project already knows.',
                        children: new Schema([
                            'address' => new SchemaKey(
                                ValueType::String,
                                default: '',
                                description: 'The sender address. An address at a domain this server is '
                                    . 'allowed to send for, or the mail will be filed as spam whatever '
                                    . 'the driver does.',
                                example: '{{env.MAIL_FROM}}',
                            ),
                            'name' => new SchemaKey(
                                ValueType::String,
                                default: '',
                                description: 'The name shown beside the address.',
                                example: 'Cyklobazar admin',
                            ),
                        ]),
                    ),
                    'host' => new SchemaKey(
                        ValueType::String,
                        description: 'SMTP host. Null while the driver does not need one.',
                        example: '{{env.MAIL_HOST}}',
                        nullable: true,
                    ),
                    'port' => new SchemaKey(ValueType::Int, default: 587, description: 'SMTP port.', example: 587),
                    'encryption' => new SchemaKey(
                        ValueType::String,
                        default: 'tls',
                        description: "How the SMTP connection is secured: 'tls' issues STARTTLS on port "
                            . "587, 'ssl' opens the socket encrypted on port 465, and 'none' sends in "
                            . 'the clear -- which means the password below crosses the network as text, '
                            . 'so it is for a server on the same machine and nothing else.',
                        example: 'ssl',
                    ),
                    'username' => new SchemaKey(
                        ValueType::String,
                        description: 'SMTP username. Null for a server that does not ask.',
                        example: '{{env.MAIL_USERNAME}}',
                        nullable: true,
                    ),
                    'password' => new SchemaKey(
                        ValueType::String,
                        description: 'SMTP password. Write it as {{env.MAIL_PASSWORD}} and keep it out '
                            . 'of git -- this file is committed, and the configuration cache is written '
                            . 'with the value resolved, which is why that cache lives outside the '
                            . 'document root.',
                        example: '{{env.MAIL_PASSWORD}}',
                        nullable: true,
                    ),
                    'timeout' => new SchemaKey(
                        ValueType::Int,
                        default: 10,
                        description: 'Seconds to wait for the SMTP server, on connecting and on every '
                            . 'reply. A request that sends mail waits for this, so it is short on '
                            . 'purpose: a mail server that has stopped answering must not hold a '
                            . 'browser open.',
                        example: 5,
                    ),
                ]),
            ),
            'assets' => new SchemaKey(
                ValueType::Array,
                description: 'Project CSS and JS, loaded after the SDK’s so they override it.',
                children: new Schema([
                    'css' => new SchemaKey(
                        ValueType::Array,
                        default: [],
                        description: 'Stylesheet URLs a project adds on top of the SDK’s own, for '
                            . 'branding or overriding the default look.',
                        example: ['/css/admin.css'],
                    ),
                    'js' => new SchemaKey(
                        ValueType::Array,
                        default: [],
                        description: 'Script URLs a project adds on top of the SDK’s own, for '
                            . 'custom widgets or page behaviour.',
                        example: ['/js/admin.js'],
                    ),
                ]),
            ),
            'pages_path' => new SchemaKey(
                ValueType::String,
                default: 'pages',
                description: 'Where page files live, relative to the configuration directory. '
                    . 'One file per page, named after the page: pages/ads.php is reachable at /p/ads.',
                example: 'pages',
            ),
            'per_page' => new SchemaKey(
                ValueType::Int,
                default: 25,
                description: 'Rows in one page of a grid, for regions that do not set their own.',
                example: 50,
                performance: 'A large value makes every grid render slower for everyone; '
                    . 'set it per region where a particular page needs more.',
            ),
            'audit' => new SchemaKey(
                ValueType::Array,
                description: 'The record of what happened: one row in ra_activity_log per write.',
                children: new Schema([
                    'enabled' => new SchemaKey(
                        ValueType::Bool,
                        default: true,
                        description: 'Records every create, update and delete with who did it and a '
                            . 'diff of what changed. On by default, because history that was not '
                            . 'collected cannot be recovered afterwards; turn it off only where '
                            . 'another system already keeps the same record.',
                        example: false,
                    ),
                ]),
            ),
            'theme' => new SchemaKey(
                ValueType::Array,
                description: 'The default look. Replace it wholesale with a stylesheet in assets.css.',
                children: new Schema([
                    'dark' => new SchemaKey(
                        ValueType::String,
                        default: 'auto',
                        description: "Dark mode: 'auto' follows the operating system, 'on' and 'off' decide. "
                            . 'Writes data-ra-theme on the document; the bundled stylesheets '
                            . 'define no dark palette yet, so today this is a hook for a '
                            . "project's own CSS rather than a switch.",
                        example: 'auto',
                    ),
                ]),
            ),
        ]);
    }
}
