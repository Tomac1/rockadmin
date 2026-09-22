<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use RockAdmin\Config\Schema;

/**
 * What a grid reads from: one table, its key, and the joins it may follow.
 *
 * Relations are declared by their full path — 'user' and 'user.company' are
 * two entries, not a nesting. Inferring the second from the first would mean
 * guessing which foreign key to follow, and a schema with two foreign keys to
 * the same table would silently get the wrong one.
 */
final class Entity
{
    /** @param array<string, Relation> $relations keyed by the relation's own name */
    public function __construct(
        public readonly string $table,
        public readonly string $key = 'id',
        public readonly array $relations = [],
    ) {
        foreach ($relations as $name => $relation) {
            if ($name !== $relation->name) {
                throw new DbException(
                    "Relation '{$name}' of table '{$table}' calls itself '{$relation->name}'. "
                    . 'A source path refers to the key, so a mismatch makes it unreachable.',
                );
            }
        }
    }

    public function hasRelation(string $name): bool
    {
        return isset($this->relations[$name]);
    }

    public function relation(string $name): Relation
    {
        if (isset($this->relations[$name])) {
            return $this->relations[$name];
        }

        $nearest = Schema::nearestOf(array_keys($this->relations), $name);
        $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

        throw new DbException("Unknown relation '{$name}' on table '{$this->table}'.{$suffix}");
    }
}
