<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * What a write did, in enough detail for a flash message to name what
 * changed and for the audit log (milestone 5, spec 9.4) to store a value
 * diff — both from the same two rows, so a write never has to be read twice
 * to answer both questions.
 */
final class WriteResult
{
    /**
     * @param array<string, mixed> $before the row as it was, empty for an insert
     * @param array<string, mixed> $after  the row as it is, empty for a delete
     */
    public function __construct(
        public readonly string $key,
        public readonly array $before = [],
        public readonly array $after = [],
    ) {
    }

    /**
     * The columns that actually differ between `$before` and `$after`.
     *
     * Both arrays are read from the database — `$before` inside the same
     * transaction as the write, `$after` once it has applied — so this never
     * compares a value the request sent against one the database returned:
     * a form that submits every field unchanged produces an empty diff, not
     * a full one.
     *
     * @return array<string, array{0: mixed, 1: mixed}> column => [before, after]
     */
    public function changed(): array
    {
        $columns = [];

        foreach ([...array_keys($this->before), ...array_keys($this->after)] as $column) {
            $columns[$column] = true;
        }

        $diff = [];

        foreach (array_keys($columns) as $column) {
            $before = $this->before[$column] ?? null;
            $after = $this->after[$column] ?? null;

            if ($before !== $after) {
                $diff[$column] = [$before, $after];
            }
        }

        return $diff;
    }
}
