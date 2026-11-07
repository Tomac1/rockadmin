<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use Throwable;

/**
 * `vendor/bin/rockadmin`: match a name, run it, return its code.
 *
 * With no arguments it prints the list, because a command line nobody can
 * discover is a command line nobody uses. With a name nothing matches it
 * prints the list too, after saying which name it did not know — and suggests
 * the nearest one, since `user:add` and `cache:rebuild` are what people
 * actually type.
 *
 * **Exceptions stop here and become messages.** Everything below this can
 * throw: a configuration file with a typo, a database that is not listening,
 * a migration that half applied. A stack trace is the wrong answer to all of
 * them on a deployment's output, so the message is printed and the trace is
 * kept for `--trace`, which is what somebody debugging the SDK itself passes.
 */
final class Application
{
    /** @var array<string, Command> */
    private array $commands = [];

    /** @param list<Command> $commands */
    public function __construct(array $commands = [])
    {
        foreach ($commands as $command) {
            $this->commands[$command->name()] = $command;
        }
    }

    /**
     * Options may come before the command name as well as after it.
     *
     * `rockadmin --bootstrap=admin/bootstrap.php migrate` is what a Composer
     * script of `rockadmin --bootstrap=...` produces once somebody runs
     * `composer admin -- migrate`, so it has to mean the same thing as
     * putting the option last. The name is the first token that is not an
     * option; everything else, in its original order, is the command's input.
     *
     * @param list<string> $argv including the script name, as PHP supplies it
     */
    public function run(array $argv, Output $output): int
    {
        $tokens = \array_slice($argv, 1);
        $leading = [];

        while ($tokens !== [] && str_starts_with($tokens[0], '--') && $tokens[0] !== '--' && $tokens[0] !== '--help') {
            $leading[] = array_shift($tokens);
        }

        $name = $tokens[0] ?? null;

        if ($name === null || $name === '--help' || $name === '-h' || $name === 'help') {
            $this->listCommands($output);

            return 0;
        }

        $command = $this->commands[$name] ?? null;

        if ($command === null) {
            $output->error("There is no command called '{$name}'.");

            $nearest = $this->nearest($name);

            if ($nearest !== null) {
                $output->line("Did you mean '{$nearest}'?");
            }

            $output->line();
            $this->listCommands($output);

            return 1;
        }

        $input = new Input([...$leading, ...\array_slice($tokens, 1)]);

        try {
            return $command->run($input, $output);
        } catch (Throwable $error) {
            $output->error($error->getMessage());

            if ($input->has('trace')) {
                $output->dim($error->getTraceAsString());
            } else {
                $output->dim('Run it again with --trace to see where that came from.');
            }

            return 1;
        }
    }

    private function listCommands(Output $output): void
    {
        $output->heading('RockAdmin');
        $output->line();
        $output->line('Usage: rockadmin <command> [options]');
        $output->line();

        $width = 0;

        foreach ($this->commands as $name => $command) {
            $width = max($width, \strlen($name));
        }

        foreach ($this->commands as $name => $command) {
            $output->line('  ' . str_pad($name, $width + 2) . $command->description());
        }

        $output->line();
        $output->dim(
            'Every command takes --bootstrap=path, before or after its name, to point at a bootstrap file '
            . 'elsewhere. RA_BOOTSTRAP or extra.rockadmin.bootstrap in composer.json says it once for good.',
        );
    }

    /**
     * The closest name, when it is close enough to be worth offering.
     *
     * A third of the length is the threshold: `migrat` suggests `migrate`,
     * and `nonsense` suggests nothing, which is better than confidently
     * offering the alphabetically luckiest command in the list.
     */
    private function nearest(string $name): ?string
    {
        $best = null;
        $bestDistance = \PHP_INT_MAX;

        foreach (array_keys($this->commands) as $candidate) {
            $distance = levenshtein($name, $candidate);

            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $bestDistance <= max(2, (int) (\strlen($name) / 3)) ? $best : null;
    }
}
