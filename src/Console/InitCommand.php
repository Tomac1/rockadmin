<?php

declare(strict_types=1);

namespace RockAdmin\Console;

/**
 * Writes the files a project keeps in git, commented, and never overwrites.
 *
 * Four of them: the configuration, the roles, an empty pages directory, and
 * the bootstrap — and the bootstrap is the one that matters, because it is
 * the file both the web entry point and every command here load. That is how
 * `migrate` and `doctor` end up operating on the installation a request would
 * get rather than on one assembled a second time from the same keys.
 *
 * **Nothing is overwritten, ever.** `init` run twice in a project that has
 * been configured would otherwise replace a year of decisions with the
 * defaults, and a flag to force it would be a flag somebody eventually
 * passes. A file that exists is reported and skipped.
 *
 * It writes no page: `make:page` reads a table and writes a real one, and a
 * commented placeholder would only be deleted.
 */
final class InitCommand implements Command
{
    public function name(): string
    {
        return 'init';
    }

    public function description(): string
    {
        return 'Writes config/rockadmin/* and a bootstrap file. Run once, commit the result.';
    }

    public function run(Input $input, Output $output): int
    {
        $root = $input->option('path') ?? (getcwd() ?: '.');
        $directory = rtrim($root, '/\\') . '/config/rockadmin';

        if (!is_dir($directory . '/pages') && !mkdir($directory . '/pages', 0o775, true) && !is_dir($directory . '/pages')) {
            throw new ConsoleException("Cannot create {$directory}/pages.");
        }

        $written = 0;
        $written += $this->write($output, $directory . '/rockadmin.php', $this->configuration());
        $written += $this->write($output, $directory . '/roles.php', $this->roles());
        $written += $this->write($output, $directory . '/bootstrap.php', $this->bootstrap());

        $output->line();

        if ($written === 0) {
            $output->dim('Everything was already there; nothing was changed.');

            return 0;
        }

        $output->heading('Next:');
        $output->line('  1. Set RA_DSN, RA_DB_USER and RA_DB_PASSWORD in your environment.');
        $output->line('  2. rockadmin migrate       # creates the ra_ tables');
        $output->line('  3. rockadmin user:create   # the first administrator');
        $output->line('  4. rockadmin doctor        # checks the rest');
        $output->line();
        $output->line('Then mount it. With a framework router:');
        $output->dim('  RockAdmin::handle($path, basePath: __DIR__, pdo: fn () => $yourPdo);');
        $output->line('Without one, an index.php of two lines:');
        $output->dim("  RockAdmin::serve(require 'config/rockadmin/bootstrap.php');");

        return 0;
    }

    private function write(Output $output, string $path, string $contents): int
    {
        if (is_file($path)) {
            $output->dim('- ' . $path . ' is already there; left alone.');

            return 0;
        }

        if (file_put_contents($path, $contents) === false) {
            throw new ConsoleException("Cannot write {$path}.");
        }

        $output->success('v Wrote ' . $path);

        return 1;
    }

    private function configuration(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            /**
             * RockAdmin's configuration. Commit it; it holds no secrets.
             *
             * Everything this file may contain is listed by `rockadmin schema`, and
             * a key that is not listed there does not exist -- an unknown key is
             * refused when the admin starts rather than ignored.
             *
             * Anything that differs between your laptop and production goes in the
             * environment and arrives through {{env.NAME}}, so that this file is the
             * same file everywhere.
             */

            return [
                'brand' => 'Admin',

                // 'path' needs a rewrite rule sending everything to one entry point.
                // 'query' needs none and works on any hosting: /admin/?ra=p/ads
                'url_mode' => 'path',

                // Never true in production: it shows what went wrong to whoever is
                // looking, and some of what went wrong is your database structure.
                'debug' => false,

                'paths' => [
                    // Relative to the project root, and it must stay outside the
                    // document root: a password-reset .eml written here is a working
                    // reset link for anybody who can fetch it.
                    'logs' => 'storage/logs/rockadmin',
                ],

                // How the admin reaches the database, when it opens its own. A project
                // whose bootstrap lends a connection can delete this block.
                'database' => [
                    'dsn' => '{{env.RA_DSN}}',
                    'user' => '{{env.RA_DB_USER}}',
                    'password' => '{{env.RA_DB_PASSWORD}}',
                ],

                // The navigation. Each key is a page in pages/, which is also its
                // permission: somebody the gate would refuse `ads.view` never sees
                // the link. `route` names one of the admin's own routes instead.
                'menu' => [
                    'dashboard' => ['label' => 'Dashboard', 'route' => 'dashboard'],
                ],

                // Where the admin is reachable from outside, for the links in mail.
                // Deliberately not taken from the request: a link built from the Host
                // header is a link whoever sent the request chose. Empty means no
                // reset mail is sent, and the form says so.
                'public_url' => '{{env.RA_PUBLIC_URL}}',

                'mail' => [
                    // 'log' writes each message to paths.logs as an .eml and sends
                    // nothing, which is the right driver until the day it is not.
                    // The others are 'smtp', 'sendmail' and 'callback'.
                    'driver' => 'log',
                    'from' => [
                        'address' => 'admin@example.com',
                        'name' => 'Admin',
                    ],
                ],
            ];

            PHP;
    }

    private function roles(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            /**
             * What each role may do. Who has which role is data, in ra_users.roles.
             *
             * Permission names are generated from your pages: a page `ads` with a
             * form region produces ads.view, ads.create, ads.update and ads.delete.
             * Naming a permission no page produces is refused when the admin starts,
             * which is how a typo stops being a silent loss of access.
             *
             * `ads.*` widens, `!ads.delete` subtracts, and subtraction always wins.
             */

            return [
                'superadmin' => [
                    'label' => 'Superadmin',
                    'permissions' => ['*'],
                ],

                // 'editor' => [
                //     'label' => 'Editor',
                //     'permissions' => ['*.view', '*.update'],
                // ],
            ];

            PHP;
    }

    private function bootstrap(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            /**
             * The installation itself: loaded by the web entry point and by every
             * `rockadmin` command, so that both operate on the same thing.
             *
             * It returns a Setup, which carries what configuration cannot -- a
             * closure, a session store, an authenticator, a storage. Under a
             * framework, require its autoloader here and lend its connection with
             * `pdo:`; a request and a command then share one connection and one
             * place holding the password.
             */

            use RockAdmin\Setup;

            $root = dirname(__DIR__, 2);
            $autoloader = $root . '/vendor/autoload.php';

            // Required here for a plain project, and already loaded under a
            // framework whose own entry point ran first -- so this file works
            // as the web bootstrap and as the one the CLI loads, which is the
            // whole reason there is only one of it.
            if (is_file($autoloader)) {
                require_once $autoloader;
            }

            return new Setup(
                basePath: $root,

                // Where the admin is mounted, as a URL path.
                baseUrl: '/admin',

                // The compiled configuration: one include instead of a directory read
                // and validated on every request. Outside the document root, because
                // it holds resolved {{env.*}} values. Never committed.
                // Build it with `rockadmin cache:build`, clear it with `cache:clear`.
                cachePath: $root . '/storage/cache/rockadmin/config.cache.php',

                // Lend the connection your application already has, rather than
                // opening a second one:
                //
                // pdo: fn () => DB::connection()->getPdo(),
            );

            PHP;
    }
}
