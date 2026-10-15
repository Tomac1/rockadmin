<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Config\Cache;
use RockAdmin\Config\Loader;

/**
 * `cache:build` and `cache:clear`, which are one piece of code.
 *
 * Two commands rather than one with a flag, because they are typed in
 * different places — `cache:clear` in a deploy script before the new code is
 * live, `cache:build` after — and a flag would make the wrong one a typo away
 * from the right one.
 *
 * Neither is required: an installation with no `cachePath` reads and
 * validates its configuration on every request, which is correct and slow.
 * What `cache:build` buys is that the first visitor after a deploy does not
 * pay for compiling it.
 */
final class CacheCommand implements Command
{
    public function __construct(private readonly bool $build)
    {
    }

    public function name(): string
    {
        return $this->build ? 'cache:build' : 'cache:clear';
    }

    public function description(): string
    {
        return $this->build
            ? 'Compiles the configuration into one file for opcache to hold.'
            : 'Deletes the compiled configuration. Run it before new code goes live.';
    }

    public function run(Input $input, Output $output): int
    {
        $setup = Bootstrap::setup($input);
        $path = $setup->cachePath;

        if ($path === null) {
            throw new ConsoleException(
                'This installation does not compile its configuration: the bootstrap passes no '
                . 'cachePath to Setup. Add one -- a file outside the document root, because the '
                . 'compiled configuration holds resolved {{env.*}} values -- and this command will '
                . 'have somewhere to write.',
            );
        }

        $cache = new Cache($path);

        if (!$this->build) {
            $cache->clear();
            $output->success('Cleared ' . $path);

            return 0;
        }

        // Built from a fresh load rather than from whatever is there, and
        // the warnings are printed rather than swallowed: compiling is the
        // last moment anybody looks at the configuration before production
        // serves it a thousand times.
        $loader = new Loader($setup->configDirectory(), $setup->env ?? static fn (string $n): ?string
            => getenv($n) === false ? null : (string) getenv($n), $setup->basePath);

        $config = $loader->load();

        foreach ($loader->warnings() as $warning) {
            $output->warning('! ' . $warning);
        }

        $cache->write($config);
        $output->success('Wrote ' . $path);

        return 0;
    }
}
