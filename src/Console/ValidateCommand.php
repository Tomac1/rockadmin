<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Config\Loader;

/**
 * Reads everything and says whether it holds together. The command CI runs.
 *
 * It loads the configuration from the files rather than from any compiled
 * copy — a build that validated the cache would validate yesterday's answer —
 * then boots the admin, which is what checks the parts the loader cannot: a
 * role naming a page that does not exist, a page naming an operation nothing
 * registers, a field accepting a type the installation forbids.
 *
 * It opens no database beyond what booting opens, and changes nothing.
 */
final class ValidateCommand implements Command
{
    public function name(): string
    {
        return 'validate';
    }

    public function description(): string
    {
        return 'Checks the configuration and the pages without changing anything. For CI.';
    }

    public function run(Input $input, Output $output): int
    {
        $setup = Bootstrap::setup($input);

        $loader = new Loader(
            $setup->configDirectory(),
            $setup->env ?? static fn (string $n): ?string => getenv($n) === false ? null : (string) getenv($n),
            $setup->basePath,
        );

        $loader->load();
        $warnings = $loader->warnings();

        // Booting is the rest of the check: the gate is built from the roles
        // and the pages together, and the operations are asserted against the
        // pages that name them. Both throw, and `Application` turns that into
        // a message and a non-zero code.
        $admin = \RockAdmin\Admin::boot($setup);
        $warnings = [...$warnings, ...$admin->warnings, ...$admin->services->pages->warnings()];

        foreach ($warnings as $warning) {
            $output->warning('! ' . $warning);
        }

        $output->success('The configuration is valid.');

        if ($warnings !== [] && $input->has('strict')) {
            $output->error('--strict, and there were warnings.');

            return 1;
        }

        return 0;
    }
}
