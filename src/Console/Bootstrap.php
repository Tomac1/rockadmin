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

    public static function setup(Input $input, ?string $workingDirectory = null): Setup
    {
        $root = $workingDirectory ?? (getcwd() ?: '.');
        $option = $input->option('bootstrap');
        $path = $option ?? $root . '/' . self::DEFAULT_PATH;

        if (!is_file($path)) {
            throw new ConsoleException(
                "No bootstrap file at {$path}. Run `rockadmin init` to write one, or point at an "
                . 'existing one with --bootstrap=path.',
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
