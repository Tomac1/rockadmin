<?php

declare(strict_types=1);

namespace RockAdmin\Console;

/**
 * What was typed, in two piles: positional arguments and `--options`.
 *
 * Deliberately small. A command line is `argv`, and every parser richer than
 * this one is a definition language for arguments that would then need
 * documenting, validating and generating help from — which is a package, and
 * this project does not install packages. What it supports is what the eight
 * commands use: `--flag`, `--name=value`, and everything else in order.
 *
 * `--` ends the options, as everywhere, so a value that looks like one can
 * still be passed.
 */
final class Input
{
    /** @var list<string> */
    private array $arguments = [];

    /** @var array<string, string|bool> */
    private array $options = [];

    /** @param list<string> $argv without the script name and without the command */
    public function __construct(array $argv)
    {
        $optionsEnded = false;

        foreach ($argv as $token) {
            if ($token === '--') {
                $optionsEnded = true;

                continue;
            }

            if (!$optionsEnded && str_starts_with($token, '--')) {
                $token = substr($token, 2);
                $separator = strpos($token, '=');

                if ($separator === false) {
                    $this->options[$token] = true;
                } else {
                    $this->options[substr($token, 0, $separator)] = substr($token, $separator + 1);
                }

                continue;
            }

            $this->arguments[] = $token;
        }
    }

    public function argument(int $position): ?string
    {
        return $this->arguments[$position] ?? null;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** Whether a flag was given at all, however it was spelled. */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }

    /**
     * An option's value.
     *
     * A flag given without one — `--force` rather than `--force=yes` — has no
     * value, and asking for it gets the default: a command that wants to know
     * whether it was there asks `has()`.
     */
    public function option(string $name, ?string $default = null): ?string
    {
        $value = $this->options[$name] ?? null;

        return \is_string($value) ? $value : $default;
    }
}
