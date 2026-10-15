<?php

declare(strict_types=1);

namespace RockAdmin\Console;

/**
 * Writing to the terminal, and asking it something.
 *
 * Colour only when the stream is a terminal: a command whose output is piped
 * into a file, a log or another program writes plain text, because escape
 * codes in a deployment log are noise somebody has to read past. `NO_COLOR`
 * is honoured, as is the convention of `TERM=dumb`.
 *
 * It takes its streams as arguments so a test can read what a command wrote
 * and answer what it asked, which is the whole of what makes these commands
 * testable.
 *
 * @phpstan-type Stream resource
 */
final class Output
{
    private const string RESET = "\033[0m";

    private const array COLOURS = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'dim' => "\033[2m",
        'bold' => "\033[1m",
    ];

    private readonly bool $colour;

    /**
     * @param resource $stream where output goes
     * @param resource $input  where `ask()` reads from
     */
    public function __construct(
        private readonly mixed $stream = \STDOUT,
        private readonly mixed $input = \STDIN,
        ?bool $colour = null,
    ) {
        $this->colour = $colour ?? $this->terminal();
    }

    public function line(string $text = ''): void
    {
        fwrite($this->stream, $text . \PHP_EOL);
    }

    public function success(string $text): void
    {
        $this->line($this->paint('green', $text));
    }

    public function error(string $text): void
    {
        $this->line($this->paint('red', $text));
    }

    public function warning(string $text): void
    {
        $this->line($this->paint('yellow', $text));
    }

    public function dim(string $text): void
    {
        $this->line($this->paint('dim', $text));
    }

    public function heading(string $text): void
    {
        $this->line($this->paint('bold', $text));
    }

    /**
     * A question, answered on the next line.
     *
     * Returns the default when nothing is typed, and when there is nothing to
     * type into — a command run from cron with no terminal gets the defaults
     * rather than blocking forever on a stream that will never deliver.
     */
    public function ask(string $question, string $default = ''): string
    {
        $suffix = $default === '' ? '' : " [{$default}]";
        fwrite($this->stream, $question . $suffix . ': ');

        $answer = fgets($this->input);

        if ($answer === false) {
            $this->line();

            return $default;
        }

        $answer = trim($answer);

        return $answer === '' ? $default : $answer;
    }

    /**
     * A question whose answer must not be echoed.
     *
     * `stty -echo` where there is a terminal to tell, and a plain read where
     * there is not — with a warning, because a password typed into a pipe is
     * visible to anybody who can see the pipe and whoever is typing it
     * deserves to know that before they do.
     */
    public function askHidden(string $question): string
    {
        if (!$this->terminal() || \PHP_OS_FAMILY === 'Windows') {
            // Windows has no stty, and faking it with a PowerShell call would
            // be a second way to read a password that only one platform ever
            // exercises. Saying so is better than a hidden prompt that is not.
            $this->warning('This terminal cannot hide what you type; the password will be visible.');

            return $this->ask($question);
        }

        shell_exec('stty -echo');

        try {
            $answer = $this->ask($question);
        } finally {
            shell_exec('stty echo');
            $this->line();
        }

        return $answer;
    }

    private function paint(string $colour, string $text): string
    {
        if (!$this->colour) {
            return $text;
        }

        return (self::COLOURS[$colour] ?? '') . $text . self::RESET;
    }

    private function terminal(): bool
    {
        if (getenv('NO_COLOR') !== false || getenv('TERM') === 'dumb') {
            return false;
        }

        return \function_exists('stream_isatty') && @stream_isatty($this->stream);
    }
}
