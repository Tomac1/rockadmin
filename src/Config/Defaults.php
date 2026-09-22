<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * Fills in the values a schema declares and the configuration left out.
 *
 * A default belongs to the schema rather than to the code that reads a key
 * (spec 6.3), so `url_mode` is 'path' even in a project that never wrote it,
 * and every reader sees the same value.
 *
 * Deliberately conservative in two ways:
 *
 * - A key the project wrote is never touched, including one it set to null.
 *   An explicit null is an instruction, not an omission.
 * - A parent the project did not write is never invented out of its children's
 *   defaults. An absent `mail` stays absent rather than becoming a half-built
 *   array of driver and port that nobody asked for; "improving" this would make
 *   `isset($config['mail'])` mean something different from what was written.
 */
final class Defaults
{
    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function apply(array $config, Schema $schema): array
    {
        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            if ($key === null) {
                continue;
            }

            if (!\array_key_exists($name, $config)) {
                if ($key->default !== null) {
                    $config[$name] = $key->default;
                }

                continue;
            }

            $written = $config[$name];

            if (\is_array($written)) {
                $config[$name] = $this->descend($written, $key);
            }
        }

        return $config;
    }

    /**
     * @param  array<array-key, mixed> $written
     * @return array<array-key, mixed>
     */
    private function descend(array $written, SchemaKey $key): array
    {
        if ($key->children !== null) {
            return $this->apply($this->keyedByName($written), $key->children);
        }

        if ($key->each === null) {
            return $written;
        }

        foreach ($written as $entryName => $entry) {
            if (\is_array($entry)) {
                $written[$entryName] = $this->apply($this->keyedByName($entry), $key->each);
            }
        }

        return $written;
    }

    /**
     * Configuration keys are strings by convention (spec 6.7), but PHP turns a
     * numeric one into an int on the way in, so they are named again here.
     *
     * @param  array<array-key, mixed> $value
     * @return array<string, mixed>
     */
    private function keyedByName(array $value): array
    {
        $named = [];

        foreach ($value as $name => $entry) {
            $named[(string) $name] = $entry;
        }

        return $named;
    }
}
