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
                    // There is no 'cache' key here, and it is worth saying why
                    // rather than leaving its absence to be noticed: where the
                    // compiled configuration lives cannot be written in the
                    // configuration, because it is read before the
                    // configuration is. It is `cachePath` on the bootstrap's
                    // `Setup`, which is the one place that is in hand first.
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
                // An unset {{env.*}} resolves to null, and this key is one
                // every project writes that way -- so null has to mean "not
                // set yet" rather than "your configuration is broken". It
                // reads as empty, which this key already has an answer for.
                nullable: true,
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
            'storage' => new SchemaKey(
                ValueType::Array,
                description: 'Where uploaded files are kept. The default driver writes them to a '
                    . 'directory and the admin serves them, which needs nothing configured but a '
                    . 'path; the s3 driver talks to Cloudflare R2 or anything else speaking the same '
                    . 'API, with no library installed.',
                children: new Schema([
                    'driver' => new SchemaKey(
                        ValueType::String,
                        default: 'local',
                        description: 'local or s3. A project that already has its own disk '
                            . 'abstraction -- Laravel Storage, Flysystem -- wires a Storage object '
                            . 'in the bootstrap instead and neither of these runs.',
                        example: 's3',
                    ),
                    'path' => new SchemaKey(
                        ValueType::String,
                        default: '',
                        description: 'For the local driver: the directory files live in, relative to '
                            . 'the project root. **It must be outside the document root.** A '
                            . 'directory of uploads inside it is a directory a stranger can fetch by '
                            . 'guessing, and on a server that runs PHP by file extension rather than '
                            . 'by location, one they may be able to execute.',
                        example: 'storage/uploads',
                    ),
                    'public_url' => new SchemaKey(
                        ValueType::String,
                        default: '',
                        description: 'Serve files from this address instead of through the admin, as '
                            . 'an absolute URL with no trailing slash. Empty -- the default -- means '
                            . 'every file goes through a route only a signed-in administrator can '
                            . 'use. Setting it is a decision that the files behind it are public to '
                            . 'anybody who learns the address, which is right for product photos and '
                            . 'wrong for scanned invoices.',
                        example: 'https://cdn.cyklobazar.cz/uploads',
                    ),
                    'bucket' => new SchemaKey(
                        ValueType::String,
                        description: 'For the s3 driver: the bucket name.',
                        example: 'cyklobazar-uploads',
                        nullable: true,
                    ),
                    'endpoint' => new SchemaKey(
                        ValueType::String,
                        description: 'For the s3 driver: the API endpoint, with scheme and no path. '
                            . 'R2 gives you one per account, and it is not the public bucket address '
                            . '-- that one goes in public_url.',
                        example: 'https://{{env.R2_ACCOUNT}}.r2.cloudflarestorage.com',
                        nullable: true,
                    ),
                    'region' => new SchemaKey(
                        ValueType::String,
                        default: 'auto',
                        description: "The region a signature is computed for. R2 uses 'auto' and so "
                            . 'does this; an S3 bucket needs its own, and getting it wrong fails '
                            . 'exactly like a wrong password because the server will not say which.',
                        example: 'eu-central-1',
                    ),
                    'key' => new SchemaKey(
                        ValueType::String,
                        description: 'For the s3 driver: the access key id.',
                        example: '{{env.R2_ACCESS_KEY_ID}}',
                        nullable: true,
                    ),
                    'secret' => new SchemaKey(
                        ValueType::String,
                        description: 'For the s3 driver: the secret. Write it as {{env.*}} and keep '
                            . 'it out of git -- this file is committed, and the configuration cache '
                            . 'is written with the value resolved, which is why that cache lives '
                            . 'outside the document root.',
                        example: '{{env.R2_SECRET_ACCESS_KEY}}',
                        nullable: true,
                    ),
                    'link_minutes' => new SchemaKey(
                        ValueType::Int,
                        default: 10,
                        description: 'How long a signed link to a private file stays good for. Long '
                            . 'enough to open a page and look at it, short enough that a URL copied '
                            . 'out of a browser history is no use tomorrow.',
                        example: 5,
                    ),
                ]),
            ),
            'uploads' => new SchemaKey(
                ValueType::Array,
                description: 'What a file field accepts. A field may narrow both of these and may '
                    . 'not widen them: whatever a page file says, nothing larger or stranger than '
                    . 'this is written anywhere.',
                children: new Schema([
                    'max_bytes' => new SchemaKey(
                        ValueType::Int,
                        default: 5_242_880,
                        description: 'The largest file accepted, in bytes. Five megabytes by default, '
                            . "which is a photograph. PHP's own upload_max_filesize and post_max_size "
                            . 'cap this from below whatever it says, and a request over that limit '
                            . 'arrives with its whole body discarded -- which the admin notices and '
                            . 'reports as a size problem rather than as a failed form.',
                        example: 2_097_152,
                    ),
                    'types' => new SchemaKey(
                        ValueType::Array,
                        default: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'],
                        description: 'The content types accepted anywhere in this admin, as an '
                            . 'allowlist. The type is read from the bytes rather than from what the '
                            . 'browser claimed, and the stored file gets the extension this admin '
                            . 'maps that type to -- so an upload called invoice.php.jpg is stored as '
                            . '.jpg whatever is inside it.',
                        example: ['image/jpeg', 'image/png'],
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
            'menu' => new SchemaKey(
                ValueType::Array,
                default: [],
                description: 'The navigation, keyed by item. The key is the item\'s identity and its '
                    . "permission: an entry keyed 'ads' links to the ads page, is hidden from "
                    . 'anybody the gate would refuse ads.view, and is marked active while that '
                    . "page is open. Say where an item goes with 'route' for one of the admin's "
                    . "own named routes or 'url' for anything else -- and with neither for the "
                    . 'common case, where the key names a page.',
                example: [
                    'dashboard' => ['label' => 'Dashboard', 'route' => 'dashboard'],
                    'ads' => ['label' => 'Ads'],
                ],
                each: self::menuItem(self::menuItem(null)),
            ),
            'database' => new SchemaKey(
                ValueType::Array,
                description: 'How to reach the database, for a project with no connection to lend. '
                    . 'A host that has one passes it to the bootstrap instead, which is the better '
                    . 'answer wherever it is available: one connection, one place holding the '
                    . 'password, and transactions that do not diverge when the project writes '
                    . "alongside the admin. A closure given to the bootstrap wins over these keys.\n\n"
                    . 'Write them as {{env.*}} placeholders. The configuration file is committed and '
                    . 'must be identical in every environment, which a literal password is not.',
                children: new Schema([
                    'dsn' => new SchemaKey(
                        ValueType::String,
                        default: '',
                        // Written as {{env.*}} by everybody, and unset on a laptop
                        // that has not been set up yet: null means not set.
                        nullable: true,
                        description: 'A PDO connection string. Empty means the admin has no database '
                            . 'of its own to open, which is a legitimate state: every page that '
                            . 'needs rows says so plainly rather than failing.',
                        example: '{{env.RA_DSN}}',
                    ),
                    'user' => new SchemaKey(
                        ValueType::String,
                        default: '',
                        // Written as {{env.*}} by everybody, and unset on a laptop
                        // that has not been set up yet: null means not set.
                        nullable: true,
                        description: 'The database user.',
                        example: '{{env.RA_DB_USER}}',
                    ),
                    'password' => new SchemaKey(
                        ValueType::String,
                        default: '',
                        // Written as {{env.*}} by everybody, and unset on a laptop
                        // that has not been set up yet: null means not set.
                        nullable: true,
                        description: 'The database password. Never a literal in a committed file.',
                        example: '{{env.RA_DB_PASSWORD}}',
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

    /**
     * One menu entry, whose children are entries of the same shape.
     *
     * The recursion is unrolled rather than expressed, because a schema that
     * referred to itself could not be built: `menuItem(menuItem(null))` is
     * two levels — a bar of items, each with one dropdown — which is what the
     * design system's navigation draws and what a third level would have no
     * markup for. Passing null is the leaf: an entry that may not nest.
     */
    private static function menuItem(?Schema $children): Schema
    {
        $keys = [
            'label' => new SchemaKey(
                ValueType::String,
                description: 'What the link says. Defaults to the key, which is rarely what you want.',
                example: 'Ads',
            ),
            'route' => new SchemaKey(
                ValueType::String,
                description: "One of the admin's own named routes, e.g. 'dashboard'.",
                example: 'dashboard',
            ),
            'url' => new SchemaKey(
                ValueType::String,
                description: 'An address, taken as written -- including an absolute one, because a '
                    . 'link out of the admin belongs in the menu too.',
                example: 'https://cyklobazar.cz',
            ),
            'icon' => new SchemaKey(
                ValueType::String,
                description: 'An icon name the theme understands. The bundled theme draws none yet.',
                example: 'box',
            ),
        ];

        if ($children !== null) {
            $keys['children'] = new SchemaKey(
                ValueType::Array,
                default: [],
                description: 'A dropdown under this item, keyed the same way and filtered by the '
                    . 'same permissions.',
                example: ['ads' => ['label' => 'Ads']],
                each: $children,
            );
        }

        return new Schema($keys);
    }
}
