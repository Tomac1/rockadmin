<?php

declare(strict_types=1);

namespace RockAdmin\Console;

/**
 * One command: a name, a line of help, and something to do.
 *
 * An interface rather than a base class, because there is nothing to inherit.
 * The two categories specification 11.1 draws — scaffolding, which writes
 * files a project commits, and environment operations, which change a
 * database or a cache — are a fact about when a command is run and not about
 * what it is made of, so they are not two interfaces.
 */
interface Command
{
    /** As it is typed: `migrate`, `user:create`, `cache:build`. */
    public function name(): string;

    /** One line, shown in the list. */
    public function description(): string;

    /**
     * What it does.
     *
     * The return value is the process's exit code, which is the only thing a
     * deployment script reads: zero is success and everything else is not.
     * A command that cannot do its job says why on the output and returns
     * non-zero — it does not throw, because a stack trace in a deploy log is
     * not a message to whoever is deploying.
     */
    public function run(Input $input, Output $output): int;
}
