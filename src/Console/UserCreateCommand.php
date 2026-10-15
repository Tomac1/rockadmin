<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Permission;
use RockAdmin\Auth\Roles;

/**
 * The first administrator, or the next one.
 *
 * Interactive by default and flag-driven when it is not, because both are
 * real: somebody installing this by hand wants to be asked, and a provisioning
 * script wants `--email= --name= --role= --password=`.
 *
 * **It refuses a role the configuration does not declare**, listing the ones
 * it does. A row carrying a role nothing grants is an administrator who can
 * sign in and do nothing, which looks like a broken admin rather than a typo
 * in a flag — and `roles.php` is the only place that knows the answer.
 *
 * The password is read without echo where the terminal allows it, and is
 * never taken from the command line unless somebody passes it there knowing
 * that `ps` and the shell history both keep it.
 */
final class UserCreateCommand implements Command
{
    public function name(): string
    {
        return 'user:create';
    }

    public function description(): string
    {
        return 'Creates an administrator in ra_users. Interactive unless given --email and --name.';
    }

    public function run(Input $input, Output $output): int
    {
        $services = Bootstrap::admin($input)->services;
        $connection = $services->connection;

        if ($connection === null) {
            throw new ConsoleException(
                'There is no database, so there is nowhere to put an administrator. '
                . 'Run `rockadmin doctor`.',
            );
        }

        $users = new AdminUsers($connection);
        $roles = Roles::fromConfig($services->config->roles(), Permission::catalogue($services->pages));

        $email = $input->option('email') ?? $output->ask('Email');
        $name = $input->option('name') ?? $output->ask('Name');

        if ($email === '' || $name === '') {
            throw new ConsoleException('An administrator needs an email address and a name.');
        }

        $available = $roles->names();

        if ($available === []) {
            throw new ConsoleException(
                'No roles are declared, so there is nothing to grant. Write config/rockadmin/roles.php '
                . 'first -- an administrator with no role can sign in and do nothing.',
            );
        }

        $role = $input->option('role') ?? $output->ask(
            'Role (' . implode(', ', $available) . ')',
            $roles->widest(),
        );

        if (!$roles->has($role)) {
            throw new ConsoleException(
                "There is no role called '{$role}'. roles.php declares: " . implode(', ', $available) . '.',
            );
        }

        $password = $input->option('password');

        if ($password === null) {
            $password = $output->askHidden('Password');
            $again = $output->askHidden('Password again');

            if ($password !== $again) {
                throw new ConsoleException('Those two passwords are not the same. Nothing was created.');
            }
        }

        if ($password === '') {
            throw new ConsoleException('An empty password would be an account nobody can sign in to.');
        }

        // The founder is the administrator an installation cannot lock itself
        // out by deleting, and there is exactly one: the first. Whether this
        // is it is a fact about the table, not a flag somebody passes.
        $founder = $users->none();

        $id = $users->create($email, $name, $password, [$role], [], founder: $founder);

        $output->success("Created administrator {$id} <{$email}> as {$role}.");

        if ($founder) {
            $output->dim('This is the founder: the one account the admin refuses to leave itself without.');
        }

        return 0;
    }
}
