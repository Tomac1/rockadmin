<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Admin;
use RockAdmin\Setup;

/**
 * How a command finds the installation it is operating on.
 *
 * **One file, shared with the web entry point.** `config/rockadmin/bootstrap.php`
 * returns a `Setup`, and both `index.php` and every command here load it — so
 * `doctor` reports the connection a request would actually open, rather than
 * one assembled from the same configuration by a second piece of code that
 * might read it differently. That is the whole reason the file exists:
 * specification 11.2 asks that `migrate` and `user:create` behave identically
 * under Laravel and plain PHP, and the way to get that is for the CLI to know
 * nothing about either.
 *
 * The file is PHP and may do anything a bootstrap does — load the framework,
 * read `.env`, open a connection — because under a framework it has to.
 */
final class Bootstrap
{
    private const string DEFAULT_PATH = 'config/rockadmin/bootstrap.php';

    public const string ENVIRONMENT = 'RA_BOOTSTRAP';

    public static function setup(Input $input, ?string $workingDirectory = null): Setup
    {
        $root = $workingDirectory ?? (getcwd() ?: '.');
        $env = getenv(self::ENVIRONMENT);
        $path = self::locate($input, $root, \is_string($env) && $env !== '' ? $env : null);

        if (!is_file($path)) {
            throw new ConsoleException(
                "No bootstrap file at {$path}. Run `rockadmin init` to write one, or point at an "
                . 'existing one: --bootstrap=path on the command, ' . self::ENVIRONMENT . '=path in the '
                . 'environment, or "extra": {"rockadmin": {"bootstrap": "path"}} in composer.json '
                . '(extra.rockadmin.bootstrap).',
            );
        }

        $setup = require $path;

        if (!$setup instanceof Setup) {
            throw new ConsoleException(
                "{$path} must return a RockAdmin\\Setup; it returned " . get_debug_type($setup) . '. '
                . 'The file is the one both the web entry point and this command line load, so '
                . 'what it returns is the installation itself.',
            );
        }

        return $setup;
    }

    /**
     * Which bootstrap file this run means, first answer wins.
     *
     * 1. `--bootstrap=path`, for one run.
     * 2. `RA_BOOTSTRAP`, for a shell or a deploy environment.
     * 3. `extra.rockadmin.bootstrap` in the project's composer.json, which is
     *    the one to use: committed, so every command on every machine finds
     *    the file without being told.
     * 4. `config/rockadmin/bootstrap.php`, which is where `init` writes
     *    outside Laravel. Under Laravel it writes `rockadmin/` and names it in
     *    composer.json, because Laravel loads everything under `config/`.
     *
     * A relative path from the environment or from composer.json resolves
     * against the project root. The option is taken as typed, like any other
     * path on a command line.
     */
    public static function locate(Input $input, string $root, ?string $environment): string
    {
        $root = rtrim($root, '/\\');
        $option = $input->option('bootstrap');

        if ($option !== null) {
            return $option;
        }

        $declared = $environment ?? self::fromComposer($root);

        if ($declared === null) {
            return $root . '/' . self::DEFAULT_PATH;
        }

        return self::isAbsolute($declared) ? $declared : $root . '/' . $declared;
    }

    private static function fromComposer(string $root): ?string
    {
        $file = $root . '/composer.json';

        if (!is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);
        $data = \is_string($contents) ? json_decode($contents, true) : null;

        if (!\is_array($data)) {
            return null;
        }

        $extra = $data['extra'] ?? null;
        $ours = \is_array($extra) ? ($extra['rockadmin'] ?? null) : null;
        $value = \is_array($ours) ? ($ours['bootstrap'] ?? null) : null;

        if ($value === null) {
            return null;
        }

        if (!\is_string($value) || $value === '') {
            throw new ConsoleException(
                "{$file} has extra.rockadmin.bootstrap set to " . get_debug_type($value) . '. It is the path '
                . 'to the bootstrap file, relative to the project: "admin/bootstrap.php".',
            );
        }

        return $value;
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\')
            || preg_match('~^[A-Za-z]:[/\\\\]~', $path) === 1;
    }

    /**
     * The booted admin, for a command that needs more than configuration.
     *
     * Booting is also the first real check of an installation: the
     * configuration is validated, the roles are checked against the pages and
     * the connection is opened. A command that merely wants the configuration
     * should ask for the setup and load it itself rather than paying for the
     * handlers.
     */
    public static function admin(Input $input, ?string $workingDirectory = null): Admin
    {
        return Admin::boot(self::setup($input, $workingDirectory));
    }
}
