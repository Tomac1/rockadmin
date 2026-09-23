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
            'mail' => new SchemaKey(
                ValueType::Array,
                description: 'How the admin sends a password reset.',
                children: new Schema([
                    'driver' => new SchemaKey(
                        ValueType::String,
                        default: 'log',
                        description: 'log, smtp, sendmail or callback.',
                        example: 'smtp',
                    ),
                    'host' => new SchemaKey(
                        ValueType::String,
                        description: 'SMTP host. Null while the driver does not need one.',
                        example: '{{env.MAIL_HOST}}',
                        nullable: true,
                    ),
                    'port' => new SchemaKey(ValueType::Int, default: 587, description: 'SMTP port.', example: 587),
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
            'theme' => new SchemaKey(
                ValueType::Array,
                description: 'The default look. Replace it wholesale with a stylesheet in assets.css.',
                children: new Schema([
                    'dark' => new SchemaKey(
                        ValueType::String,
                        default: 'auto',
                        description: "Dark mode: 'auto' follows the operating system, 'on' and 'off' decide.",
                        example: 'auto',
                    ),
                ]),
            ),
        ]);
    }
}
