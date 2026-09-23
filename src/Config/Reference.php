<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * The configuration reference, generated from the schema.
 *
 * This project's premise is that admin configuration is mostly written by
 * agents reading documentation, so a key that exists in code and not in the
 * documentation is a feature nobody can use. Writing the reference by hand
 * would mean maintaining the same list twice and discovering the drift months
 * later; generating it means the schema is the only place a key is declared,
 * and a test comparing the committed file against this output fails the build
 * the moment they disagree.
 */
final class Reference
{
    /** Rendered Markdown for a whole schema, ready to commit. */
    public static function markdown(Schema $schema, string $title, string $intro = ''): string
    {
        $out = "# {$title}\n\n";
        $out .= "<!-- Generated from the schema by `composer run docs:reference`. Do not edit by hand. -->\n\n";

        if ($intro !== '') {
            $out .= $intro . "\n\n";
        }

        return $out . self::section($schema, '');
    }

    /** One level of keys, and then the levels below it. */
    private static function section(Schema $schema, string $prefix): string
    {
        $out = self::table($schema, $prefix);

        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            if ($key === null) {
                continue;
            }

            $path = $prefix === '' ? $name : $prefix . '.' . $name;

            // A key with children is a block of its own keys; a key with `each`
            // describes what every entry of a list looks like. Both deserve
            // their own heading, because that is the level someone writes at.
            if ($key->children !== null) {
                $out .= "\n## `{$path}`\n\n" . self::describe($key) . "\n" . self::section($key->children, $path);
            }

            if ($key->each !== null) {
                $out .= "\n## `{$path}.*`\n\nEvery entry of `{$path}`.\n\n"
                    . self::section($key->each, $path . '.*');
            }
        }

        return $out;
    }

    private static function table(Schema $schema, string $prefix): string
    {
        $rows = [];

        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            if ($key === null || $key->children !== null || $key->each !== null) {
                // A block's own row would say "array" and nothing useful; its
                // heading and its children say everything instead.
                continue;
            }

            $rows[] = '| `' . $name . '` | ' . self::type($key) . ' | ' . self::literal($key->default)
                . ' | ' . self::literal($key->example) . ' | ' . self::notes($key) . ' |';
        }

        if ($rows === []) {
            return '';
        }

        return "| Key | Type | Default | Example | What it does |\n"
            . "| --- | --- | --- | --- | --- |\n"
            . implode("\n", $rows) . "\n";
    }

    private static function describe(SchemaKey $key): string
    {
        return $key->description === '' ? '' : self::escape($key->description) . "\n";
    }

    private static function type(SchemaKey $key): string
    {
        $type = $key->type->value;

        if ($key->nullable) {
            $type .= ' or null';
        }

        return $type;
    }

    /**
     * Everything a reader needs beyond the type: what the key is for, whether
     * it must be present, whether it may be left out of a cached build, and
     * what it costs to omit.
     */
    private static function notes(SchemaKey $key): string
    {
        $notes = [self::escape($key->description)];

        if ($key->required) {
            $notes[] = '**Required.**';
        }

        if ($key->deferrable) {
            $notes[] = 'May be resolved per request rather than at load.';
        }

        if ($key->performance !== null) {
            $notes[] = '*Performance:* ' . self::escape($key->performance);
        }

        return trim(implode(' ', array_filter($notes, static fn (string $note): bool => $note !== '')));
    }

    /** A configuration value as someone would type it into a PHP file. */
    private static function literal(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            $value === true => '`true`',
            $value === false => '`false`',
            $value === [] => '`[]`',
            \is_int($value), \is_float($value) => '`' . $value . '`',
            \is_string($value) => '`' . self::escape($value) . '`',
            \is_array($value) => '`' . self::escape(self::inlineArray($value)) . '`',
            default => '—',
        };
    }

    /** @param array<array-key, mixed> $value */
    private static function inlineArray(array $value): string
    {
        $parts = [];

        foreach ($value as $name => $item) {
            $rendered = match (true) {
                \is_string($item) => "'" . $item . "'",
                \is_int($item), \is_float($item) => (string) $item,
                \is_bool($item) => $item ? 'true' : 'false',
                $item === null => 'null',
                \is_array($item) => '[…]',
                default => '…',
            };

            $parts[] = \is_int($name) ? $rendered : "'{$name}' => {$rendered}";
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * A pipe would end a table cell early and a newline would end the row, so
     * both are neutralised. Nothing else is escaped: the descriptions are
     * prose this project writes, not input from anywhere.
     */
    private static function escape(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }
}
