<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Auth\AdminUsers;
use RockAdmin\Db\Migrator;
use RockAdmin\Http\Router;
use Throwable;

/**
 * Checks everything that is usually wrong on a first deploy.
 *
 * It exists because the alternative is a white page and an afternoon. Each
 * check is one line of output and the command's exit code is non-zero if any
 * of them failed — so it is also something a deploy script can run before it
 * switches the symlink.
 *
 * **It boots the admin rather than re-deriving anything.** A doctor that
 * opened its own connection from the same configuration would be a second
 * piece of code reading it, and the one bug it could never find is the one
 * where those two disagree. What it reports is what a request would get.
 */
final class DoctorCommand implements Command
{
    private const string PHP_MINIMUM = '8.4';

    public function name(): string
    {
        return 'doctor';
    }

    public function description(): string
    {
        return 'Checks PHP, the configuration, the writable paths, the database and the first admin.';
    }

    public function run(Input $input, Output $output): int
    {
        $failures = 0;

        $failures += $this->check(
            $output,
            version_compare(\PHP_VERSION, self::PHP_MINIMUM, '>='),
            'PHP ' . \PHP_VERSION,
            'PHP ' . \PHP_VERSION . ' is older than the ' . self::PHP_MINIMUM . ' this SDK needs.',
        );

        foreach (['pdo', 'json', 'mbstring'] as $extension) {
            $failures += $this->check(
                $output,
                \extension_loaded($extension),
                "ext-{$extension} is loaded",
                "ext-{$extension} is missing, and it is one of the four this SDK requires.",
            );
        }

        foreach (['curl' => 'S3 uploads fall back to streams', 'fileinfo' => 'file types are detected by signature alone'] as $extension => $consequence) {
            if (!\extension_loaded($extension)) {
                $output->dim("  - ext-{$extension} is not loaded; {$consequence}. That is supported, not a problem.");
            }
        }

        // Everything past here needs the installation itself, so a bootstrap
        // that cannot be loaded ends the run: the remaining checks would all
        // fail for the same single reason and say so eight times.
        try {
            $admin = Bootstrap::admin($input);
        } catch (Throwable $error) {
            $output->error('x The admin could not be started: ' . $error->getMessage());

            return 1;
        }

        $output->success('v Configuration loads and the roles match the pages');

        foreach ($admin->warnings as $warning) {
            $output->warning('! ' . $warning);
        }

        $services = $admin->services;

        foreach (['paths.logs'] as $key) {
            $path = $services->config->get($key, '');

            if (!\is_string($path) || $path === '') {
                $output->dim("  - {$key} is not set.");

                continue;
            }

            $failures += $this->check(
                $output,
                $this->writable($path),
                "{$key} is writable ({$path})",
                "{$key} points at {$path}, which does not exist and could not be created, or is not writable.",
            );

            if ($this->insideDocumentRoot($path)) {
                $output->warning(
                    "! {$key} looks like it is inside the document root. A reset-password .eml there "
                    . 'is a working reset link anybody can fetch.',
                );
            }
        }

        $connection = $services->connection;

        if ($connection === null) {
            $output->error(
                'x There is no database. Either database.dsn is empty, the bootstrap handed over no '
                . 'connection, or the one it handed over could not be opened.',
            );

            ++$failures;
        } else {
            $output->success('v Database reachable (' . $connection->dialect()->name() . ')');

            $migrator = new Migrator($connection);
            $pending = $migrator->pending();

            $failures += $this->check(
                $output,
                $pending === [],
                'Migrations are up to date (' . \count($migrator->applied()) . ' applied)',
                \count($pending) . ' migration(s) have not been applied. Run `rockadmin migrate`.',
            );

            if ($pending !== []) {
                // Asking ra_users anything before the migration that creates
                // it is a crash where a plain sentence belongs -- and the
                // line above has already said what to do about it.
                $output->dim('  - Skipping the administrator check until the migrations have run.');
            } elseif ((new AdminUsers($connection))->none()) {
                $output->warning(
                    '! There are no administrators yet. Run `rockadmin user:create`, or open /_setup, '
                    . 'which is reachable only while this is true.',
                );
            } else {
                $output->success('v At least one administrator exists');
            }
        }

        foreach (Router::routeNames() as $route) {
            // `diagnostics` is specification 12's development console, which
            // is not built. Every other route in the table having nothing
            // behind it would be a hole somebody finds by opening it.
            if ($route !== 'diagnostics' && !$admin->handles($route)) {
                $output->warning("! Nothing is registered for the '{$route}' route.");
            }
        }

        $output->line();

        if ($failures === 0) {
            $output->success('Nothing to fix.');

            return 0;
        }

        $output->error($failures . ' problem(s) to fix.');

        return 1;
    }

    private function check(Output $output, bool $passed, string $good, string $bad): int
    {
        if ($passed) {
            $output->success('v ' . $good);

            return 0;
        }

        $output->error('x ' . $bad);

        return 1;
    }

    private function writable(string $path): bool
    {
        if (!is_dir($path) && !@mkdir($path, 0o775, true) && !is_dir($path)) {
            return false;
        }

        return is_writable($path);
    }

    /**
     * Whether a path looks web-reachable.
     *
     * A guess, and it says so by being a warning rather than a failure: the
     * document root is the web server's business and nothing here can read
     * its configuration. What it can see is the names everybody uses.
     */
    private function insideDocumentRoot(string $path): bool
    {
        $normalised = str_replace('\\', '/', $path);

        foreach (['/public/', '/public_html/', '/web/', '/www/', '/htdocs/'] as $segment) {
            if (str_contains($normalised . '/', $segment)) {
                return true;
            }
        }

        return false;
    }
}
