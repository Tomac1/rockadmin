<?php

declare(strict_types=1);

namespace RockAdmin\Console;

use RockAdmin\Db\Connection;

/**
 * Reads a table and writes a working page for it.
 *
 * `rockadmin make:page ads --table=ads`, and `/p/ads` then lists, filters,
 * sorts, creates, edits and deletes — which is the point: an agent or a
 * person starts from a page that works and takes things out, rather than
 * from an empty file and the reference.
 *
 * **The types are guesses, and the file says so.** A column's declared type
 * narrows it a long way — `int`, `timestamp`, `boolean` — and its name
 * narrows the rest: `price` is money rather than an integer, `*_at` is a
 * datetime, `email` and `password` are fields of their own. A guess that is
 * wrong is one word to change in a file that is already yours; a scaffolder
 * that refused to guess would leave the whole file to write.
 *
 * It never overwrites. A page that exists is a page somebody edited.
 */
final class MakePageCommand implements Command
{
    /** Columns a form never offers, because the database or the admin owns them. */
    private const array NOT_EDITABLE = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Columns a scaffolder leaves out of both the grid and the form.
     *
     * Found by scaffolding `ra_users` and reading what came out: a `password`
     * column became a text column, which would have drawn everybody's hash in
     * a grid. The form half is worse -- a `password` *field* over a column the
     * page does not know to hash writes the plaintext in. Neither is a thing
     * to generate and hope somebody notices, so both are left out and the
     * file says they were.
     */
    private const array SECRETS = ['password', 'token', 'secret', 'hash', 'api_key'];

    public function name(): string
    {
        return 'make:page';
    }

    public function description(): string
    {
        return 'Writes a page from a table: make:page ads --table=ads.';
    }

    public function run(Input $input, Output $output): int
    {
        $name = $input->argument(0);

        if ($name === null || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new ConsoleException(
                'Give the page a name made of lowercase letters, digits and underscores: '
                . '`rockadmin make:page ads --table=ads`. It becomes the file name, the URL '
                . 'at /p/<name>, and the prefix of every permission the page produces.',
            );
        }

        $setup = Bootstrap::setup($input);
        $admin = \RockAdmin\Admin::boot($setup);
        $connection = $admin->services->connection;

        if (!$connection instanceof Connection) {
            throw new ConsoleException(
                'There is no database to read the table from. Run `rockadmin doctor`.',
            );
        }

        $table = $input->option('table') ?? $name;
        $columns = $this->describe($connection, $table);

        $pagesPath = $admin->services->config->get('pages_path', 'pages');
        $directory = $setup->configDirectory() . '/' . (\is_string($pagesPath) ? $pagesPath : 'pages');
        $path = $directory . '/' . $name . '.php';

        if (is_file($path)) {
            throw new ConsoleException(
                "{$path} already exists, and a page that exists is a page somebody edited. "
                . 'Delete it first if you really want it regenerated.',
            );
        }

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new ConsoleException("Cannot create {$directory}.");
        }

        if (file_put_contents($path, $this->page($name, $table, $columns)) === false) {
            throw new ConsoleException("Cannot write {$path}.");
        }

        $output->success('v Wrote ' . $path);
        $output->line();
        $output->line('Add it to the menu in rockadmin.php:');
        $output->dim("    '{$name}' => ['label' => '" . $this->title($name) . "'],");
        $output->line('and to a role in roles.php, or nobody can open it:');
        $output->dim("    'permissions' => ['{$name}.*'],");

        return 0;
    }

    /**
     * The table's columns, with whatever the server said about each.
     *
     * Both servers answer with a row per column; they disagree on the keys,
     * which is the same disagreement `Connection::columns()` already absorbs.
     *
     * @return list<array{name: string, type: string}>
     */
    private function describe(Connection $connection, string $table): array
    {
        $columns = [];

        foreach ($connection->select($connection->dialect()->columns($table)) as $row) {
            $name = $row['column_name'] ?? $row['Field'] ?? null;
            $type = $row['data_type'] ?? $row['Type'] ?? '';

            if (\is_string($name) && $name !== '') {
                $columns[] = ['name' => $name, 'type' => \is_string($type) ? strtolower($type) : ''];
            }
        }

        if ($columns === []) {
            throw new ConsoleException(
                "Table '{$table}' has no columns this connection can see. It may not exist, or the "
                . 'role may have no privilege on it -- PostgreSQL hides the one as the other.',
            );
        }

        return $columns;
    }

    /** @param list<array{name: string, type: string}> $columns */
    private function page(string $name, string $table, array $columns): string
    {
        $key = $this->key($columns);
        $gridLines = [];
        $formLines = [];

        $skipped = [];

        foreach ($columns as $column) {
            if ($this->looksSecret($column['name'])) {
                $skipped[] = $column['name'];

                continue;
            }

            $columnType = $this->columnType($column);
            $label = $this->title($column['name']);

            $extra = $column['name'] === $key ? ", 'width' => '80px'" : '';

            if ($columnType === 'text' && $column['name'] !== $key) {
                $extra = ", 'sortable' => true, 'filter' => ['type' => 'text', 'op' => 'contains']";
            }

            $gridLines[] = \sprintf(
                "                '%s' => ['type' => '%s', 'label' => '%s'%s],",
                $column['name'],
                $columnType,
                $label,
                $extra,
            );

            if (\in_array($column['name'], self::NOT_EDITABLE, true) || $column['name'] === $key) {
                continue;
            }

            $formLines[] = \sprintf(
                "                    '%s' => ['type' => '%s', 'label' => '%s'],",
                $column['name'],
                $this->fieldType($column),
                $label,
            );
        }

        $grid = implode("\n", $gridLines);
        $form = implode("\n", $formLines);
        $title = $this->title($name);
        $left = $skipped === [] ? '' : \sprintf(
            "\n *\n * Left out: %s. A column that looks like a secret is not drawn in a\n"
            . " * grid and not offered in a form -- the first would render a hash, and the\n"
            . ' * second would write a plaintext one. Add it deliberately if you mean to.',
            implode(', ', $skipped),
        );

        return <<<PHP
            <?php

            declare(strict_types=1);

            /**
             * The {$title} page, written by `rockadmin make:page` from `{$table}`.
             *
             * The column and field types are guesses made from the table: they are
             * right often enough to start from and wrong often enough to read. Every
             * key here is listed by `rockadmin schema pages`.
             *
             * This page produces the permissions {$name}.view, {$name}.create,
             * {$name}.update and {$name}.delete. Until a role in roles.php names one
             * of them, nobody can open it -- which is the intended order.{$left}
             */

            return [
                'title' => '{$title}',
                'layout' => 'single',

                'entity' => ['table' => '{$table}', 'key' => '{$key}'],

                // What a row offers. `edit` is a link to this page's own edit
                // screen; `bin` posts the built-in delete, and asks first.
                'actions' => [
                    'edit' => [
                        'type' => 'link',
                        'to' => '@page:{$name}/{id}/edit',
                        'label' => 'Edit',
                        'permission' => '{$name}.update',
                    ],
                    'bin' => [
                        'type' => 'post',
                        'to' => '@action:delete',
                        'label' => 'Delete',
                        'confirm' => 'Delete this row? This cannot be undone.',
                        'permission' => '{$name}.delete',
                    ],
                ],

                'regions' => [
                    'grid' => [
                        'type' => 'list',
                        'per_page' => 25,
                        'columns' => [
            {$grid}
                            // An actions column is an ordinary column: where it sits
                            // is where the row's controls are drawn.
                            'tools' => [
                                'type' => 'actions',
                                'label' => '',
                                'items' => ['edit', 'bin'],
                            ],
                        ],
                    ],

                    'preview' => [
                        'type' => 'preview',
                    ],

                    // One region draws create, edit and copy.
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => [
            {$form}
                            ],
                        ],
                    ],
                ],
            ];

            PHP;
    }

    /** @param list<array{name: string, type: string}> $columns */
    private function key(array $columns): string
    {
        foreach ($columns as $column) {
            if ($column['name'] === 'id') {
                return 'id';
            }
        }

        // No `id`: the first column is the best guess a scaffolder can make,
        // and naming the wrong key is visible immediately -- every row links
        // to the same place.
        return $columns[0]['name'];
    }

    /** @param array{name: string, type: string} $column */
    private function columnType(array $column): string
    {
        $name = $column['name'];
        $type = $column['type'];

        return match (true) {
            str_contains($type, 'bool') || str_starts_with($name, 'is_') || str_starts_with($name, 'has_') => 'bool',
            str_contains($type, 'timestamp') || str_contains($type, 'datetime') || str_ends_with($name, '_at') => 'datetime',
            str_contains($type, 'json') => 'json',
            $this->looksLikeMoney($name) => 'money',
            str_contains($type, 'int') || str_contains($type, 'numeric') || str_contains($type, 'decimal') => 'int',
            default => 'text',
        };
    }

    /** @param array{name: string, type: string} $column */
    private function fieldType(array $column): string
    {
        $name = $column['name'];
        $type = $column['type'];

        return match (true) {
            str_contains($type, 'bool') || str_starts_with($name, 'is_') || str_starts_with($name, 'has_') => 'checkbox',
            str_contains($type, 'timestamp') || str_contains($type, 'datetime') || str_ends_with($name, '_at') => 'datetime',
            str_contains($type, 'date') => 'date',
            str_contains($name, 'password') => 'password',
            str_contains($type, 'text') && !str_contains($type, 'varchar') => 'textarea',
            str_contains($type, 'int') || str_contains($type, 'numeric') || str_contains($type, 'decimal') => 'number',
            default => 'text',
        };
    }

    private function looksSecret(string $name): bool
    {
        foreach (self::SECRETS as $word) {
            if (str_contains($name, $word)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeMoney(string $name): bool
    {
        foreach (['price', 'amount', 'total', 'cost', 'fee', 'salary'] as $word) {
            if (str_contains($name, $word)) {
                return true;
            }
        }

        return false;
    }

    /** `created_at` becomes `Created at`, which is a label worth changing and readable until then. */
    private function title(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }
}
