<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Console\Application;
use RockAdmin\Console\Command;
use RockAdmin\Console\ConsoleException;
use RockAdmin\Console\Input;
use RockAdmin\Console\Output;

/**
 * The command line's own machinery: what was typed, and what comes back.
 *
 * The commands themselves are tested where what they do is — against a
 * database, or a directory — and this is the part that has to be right before
 * any of that: an argument read as an option, or an exception reaching a
 * deployment log as a stack trace, would make every command wrong in the same
 * way.
 */
#[CoversClass(Input::class)]
#[CoversClass(Application::class)]
#[CoversClass(Output::class)]
final class ConsoleTest extends TestCase
{
    public function testOptionsAndArgumentsAreTwoPiles(): void
    {
        $input = new Input(['ads', '--table=ra_users', '--dry-run', 'extra']);

        self::assertSame('ads', $input->argument(0));
        self::assertSame('extra', $input->argument(1));
        self::assertSame('ra_users', $input->option('table'));
        self::assertTrue($input->has('dry-run'));

        // A flag given without a value has none, which is different from
        // having an empty one: a command that wants to know whether it was
        // there asks has().
        self::assertNull($input->option('dry-run'));
        self::assertSame('fallback', $input->option('missing', 'fallback'));
    }

    public function testDoubleDashEndsTheOptions(): void
    {
        $input = new Input(['--real=1', '--', '--not-an-option']);

        self::assertSame('1', $input->option('real'));
        self::assertSame(['--not-an-option'], $input->arguments());
        self::assertFalse($input->has('not-an-option'));
    }

    public function testWithNoArgumentsItPrintsTheList(): void
    {
        [$code, $text] = $this->execute(['rockadmin']);

        self::assertSame(0, $code);
        self::assertStringContainsString('doctor', $text);
        self::assertStringContainsString('Usage: rockadmin', $text);
    }

    public function testAnUnknownNameSuggestsTheNearestOne(): void
    {
        [$code, $text] = $this->execute(['rockadmin', 'doctr']);

        self::assertSame(1, $code);
        self::assertStringContainsString("no command called 'doctr'", $text);
        self::assertStringContainsString("Did you mean 'doctor'?", $text);
    }

    public function testANameNothingResemblesSuggestsNothing(): void
    {
        [$code, $text] = $this->execute(['rockadmin', 'photosynthesis']);

        self::assertSame(1, $code);
        self::assertStringNotContainsString('Did you mean', $text);
    }

    /**
     * A failure is a sentence and a non-zero code, never a stack trace.
     *
     * Everything below a command can throw -- a configuration typo, a
     * database that is not listening, a half-applied migration -- and a
     * deployment's output is the wrong place for any of them to arrive as
     * PHP. The trace is still there behind --trace, for whoever is debugging
     * the SDK rather than their installation.
     */
    public function testAnExceptionBecomesAMessageAndAnExitCode(): void
    {
        [$code, $text] = $this->execute(['rockadmin', 'boom']);

        self::assertSame(1, $code);
        self::assertStringContainsString('nowhere to put an administrator', $text);
        self::assertStringNotContainsString('#0 ', $text);
        self::assertStringContainsString('--trace', $text);

        [, $traced] = $this->execute(['rockadmin', 'boom', '--trace']);

        self::assertStringContainsString('#0 ', $traced);
    }

    /**
     * @param  list<string>      $argv
     * @return array{int, string}
     */
    private function execute(array $argv): array
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $application = new Application([
            new class () implements Command {
                public function name(): string
                {
                    return 'doctor';
                }

                public function description(): string
                {
                    return 'Checks things.';
                }

                public function run(Input $input, Output $output): int
                {
                    return 0;
                }
            },
            new class () implements Command {
                public function name(): string
                {
                    return 'boom';
                }

                public function description(): string
                {
                    return 'Fails on purpose.';
                }

                public function run(Input $input, Output $output): int
                {
                    throw new ConsoleException('There is nowhere to put an administrator.');
                }
            },
        ]);

        $code = $application->run($argv, new Output($stream, $stream, colour: false));

        rewind($stream);
        $text = stream_get_contents($stream);
        fclose($stream);

        return [$code, \is_string($text) ? $text : ''];
    }
}
