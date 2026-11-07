<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * The compiled configuration: one include, sitting in opcache.
 *
 * In production this replaces reading a directory of files and validating
 * every key on every request. Placeholder, EnumReference and EnumOption
 * survive the round trip through __set_state(); a closure cannot, which is the
 * reason configuration holds data and the host supplies services.
 *
 * **It goes stale on its own when a configuration file changes.** The file
 * records what the configuration directories held when it was written: each
 * directory's listing, and each file's modification time and size. A read
 * that finds any of that different returns null, and the caller rebuilds it.
 * Without this, the cache written on the first request kept serving the old
 * configuration after `rockadmin.php` was edited, until somebody remembered
 * `cache:clear`; the first project to use it lost time to exactly that. The
 * price is a listing of two directories and a stat per file on each request,
 * where the cache exists to avoid parsing and validating them all.
 *
 * What it cannot see is the environment: a changed `{{env.*}}` value is
 * resolved into the file and needs `cache:clear`, as before.
 */
final class Cache
{
    /** Marks the current file format, which carries the fingerprint beside the configuration. */
    private const int FORMAT = 2;

    public function __construct(private readonly string $file)
    {
    }

    public function read(): ?Config
    {
        if (!is_file($this->file)) {
            return null;
        }

        $restored = require $this->file;

        // A file written before the fingerprint existed returns the bare
        // Config. It cannot say whether it is stale, so it is rebuilt.
        if ($restored instanceof Config) {
            return null;
        }

        $config = \is_array($restored) && ($restored['format'] ?? null) === self::FORMAT
            ? ($restored['config'] ?? null)
            : null;

        if (!$config instanceof Config) {
            throw new ConfigException(
                "{$this->file} is not a compiled configuration. Delete it and build it again.",
            );
        }

        // A file written by an older RockAdmin restores an object missing
        // whatever the class has gained since -- valid-looking and quietly
        // incomplete. Treated as absent so the caller rebuilds it, rather
        // than thrown: an upgrade that empties a cache is a slow request, an
        // upgrade that fatals is an outage.
        //
        // This covers what Config itself gained. A class it holds -- Enums,
        // today -- whose own __set_state() gains a required key still fatals
        // inside the require above, before anything here can look at it.
        // Fixing that means the same version guard on each of them, and is
        // worth doing the first time one of them changes shape.
        if (!$config->isCurrent()) {
            return null;
        }

        $sources = $restored['sources'] ?? null;
        $directories = \is_array($sources) ? array_values(array_filter(array_keys($sources), \is_string(...))) : [];

        if (!\is_array($sources) || self::fingerprint($directories) !== $sources) {
            return null;
        }

        return $config;
    }

    /**
     * The directories a configuration is read from: its own, and the pages
     * directory when `pages_path` puts it somewhere else.
     *
     * @return list<string>
     */
    public static function directoriesOf(string $configDirectory, Config $config): array
    {
        $pages = $config->get('pages_path', 'pages');
        $directories = [rtrim($configDirectory, '/\\')];

        if (\is_string($pages) && $pages !== '') {
            $directories[] = $directories[0] . '/' . trim($pages, '/\\');
        }

        return $directories;
    }

    /**
     * What these directories hold, as one comparable value: per directory,
     * its sorted listing of `.php` files and subdirectories, and per file
     * its modification time and size. Subdirectories are followed one level
     * at a time, so `pages/` under the configuration directory is covered.
     *
     * @param  list<string>                 $directories
     * @return array<string, array<string, string>>
     */
    public static function fingerprint(array $directories): array
    {
        $result = [];
        $queue = $directories;

        while ($queue !== []) {
            $directory = array_shift($queue);

            if (isset($result[$directory]) || !is_dir($directory)) {
                continue;
            }

            $entries = scandir($directory) ?: [];
            sort($entries);
            $seen = [];

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory . '/' . $entry;

                if (is_dir($path)) {
                    $seen[$entry] = 'dir';
                    $queue[] = $path;

                    continue;
                }

                if (str_ends_with($entry, '.php')) {
                    $seen[$entry] = (string) filemtime($path) . ':' . (string) filesize($path);
                }
            }

            $result[$directory] = $seen;
        }

        return $result;
    }

    /**
     * @param list<string> $directories where the configuration was read from (`directoriesOf()`),
     *                                  so a later read can tell whether it has changed since
     */
    public function write(Config $config, array $directories = []): void
    {
        $this->assertExportable($config->all(), '');

        $source = \sprintf(
            "<?php\n\n// Generated by RockAdmin. Do not edit; rebuild it instead.\n\ndeclare(strict_types=1);\n\n"
            . "return ['format' => %d, 'sources' => %s, 'config' => %s];\n",
            self::FORMAT,
            var_export(self::fingerprint($directories), true),
            var_export($config, true),
        );

        $directory = \dirname($this->file);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new ConfigException("Cannot create the cache directory: {$directory}");
        }

        $temporary = $this->file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        $written = file_put_contents($temporary, $source, LOCK_EX);

        // A short write -- a full disk -- returns a byte count, not false. Renaming
        // a truncated file into place would make every request fatal on a parse
        // error, which is the failure the atomic rename exists to prevent.
        if ($written !== \strlen($source)) {
            @unlink($temporary);

            throw new ConfigException("Cannot write the configuration cache: {$temporary}");
        }

        // The compiled cache can hold resolved {{env.*}} values, which is to say
        // secrets, so it is not left at whatever the umask allows.
        chmod($temporary, 0o600);

        // Renaming within a directory is atomic, so a request served during a deploy
        // includes either the whole old file or the whole new one. Writing in place
        // would let it include a half-written one, and `require` ignores the lock.
        if (!rename($temporary, $this->file)) {
            @unlink($temporary);

            throw new ConfigException("Cannot replace the configuration cache: {$this->file}");
        }
    }

    public function clear(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * Refuses anything var_export() cannot round-trip, naming where it is.
     *
     * Without this the failure would be an unreadable generated file at the
     * next request, rather than a message pointing at the offending key.
     *
     * @param array<array-key, mixed> $value
     */
    private function assertExportable(array $value, string $prefix): void
    {
        foreach ($value as $name => $entry) {
            $path = $prefix === '' ? (string) $name : "{$prefix}.{$name}";

            if (\is_array($entry)) {
                $this->assertExportable($entry, $path);

                continue;
            }

            if ($entry === null || \is_scalar($entry)) {
                continue;
            }

            if (\is_object($entry) && method_exists($entry, '__set_state')) {
                continue;
            }

            throw new ConfigException(\sprintf(
                "Configuration value at '%s' is a %s, which cannot be cached. "
                . 'Configuration holds data; services such as a connection, a '
                . 'mailer or a callback are given to the bootstrap instead.',
                $path,
                get_debug_type($entry),
            ));
        }
    }
}
