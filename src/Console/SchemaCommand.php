<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Auth\RoleSchema;
use RockAdmin\Config\Reference;
use RockAdmin\Config\RootSchema;
use RockAdmin\Page\PageSchema;

/**
 * Prints what the configuration may contain.
 *
 * The same markdown `docs/reference/` is generated from, written to the
 * terminal — so the answer to "what can I put in this file" is available on a
 * server, in an installation that was vendored years ago, without a browser
 * and without guessing which version of the documentation matches the code.
 *
 * It is generated from the schema rather than kept beside it, which is the
 * whole arrangement rule 5 describes: a key nobody declared cannot be used,
 * and a key nobody documented cannot exist.
 */
final class SchemaCommand implements Command
{
    public function name(): string
    {
        return 'schema';
    }

    public function description(): string
    {
        return 'Prints the configuration reference: rockadmin.php, pages, or roles.';
    }

    public function run(Input $input, Output $output): int
    {
        $what = $input->argument(0) ?? 'config';

        $markdown = match ($what) {
            'config' => Reference::markdown(RootSchema::create(), 'rockadmin.php', ''),
            'pages' => Reference::markdown(PageSchema::create(), 'A page file', ''),
            'roles' => Reference::markdown(RoleSchema::create(), 'roles.php', ''),
            default => throw new ConsoleException(
                "There is no schema called '{$what}'. It is one of: config, pages, roles.",
            ),
        };

        $output->line($markdown);

        return 0;
    }
}
