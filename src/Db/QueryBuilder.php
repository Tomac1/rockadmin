<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Composes SQL. It never executes, and it never reads a request.
 *
 * Keeping composition separate from execution is what makes the SQL a grid
 * produces answerable by a unit test that never opens a socket, and it is why
 * the development console can show a statement before it runs.
 */
final class QueryBuilder
{
    public function __construct(private readonly Dialect $dialect)
    {
    }

    public function rows(Query $query): Sql
    {
        if ($query->columns === []) {
            throw new DbException(
                "A query on '{$query->entity->table}' needs at least one column.",
            );
        }

        $selects = [];
        $bindings = [];
        $joins = [];

        foreach ($query->columns as $alias => $source) {
            $path = SourcePath::parse($source);

            foreach ($path->joins() as $join) {
                $joins[$join] = true;
            }

            $expression = $this->expression($query->entity, $path);
            $bindings = [...$bindings, ...$expression->bindings];
            $selects[] = $expression->text . ' AS ' . $this->dialect->quoteIdentifier((string) $alias);
        }

        $text = 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $this->dialect->quoteIdentifier($query->entity->table)
            . $this->joins($query->entity, array_keys($joins));

        return new Sql($text, $bindings);
    }

    /**
     * The SQL expression a source path resolves to, with any bindings it needs.
     *
     * A relation's table is used as the qualifier because a relation is joined
     * once, under its own table name.
     */
    private function expression(Entity $entity, SourcePath $path): Sql
    {
        $table = $path->relation === null
            ? $entity->table
            : $entity->relation($path->relation)->table;

        $qualified = $this->dialect->qualify($table, $path->column);

        if ($path->json === []) {
            return new Sql($qualified);
        }

        return $this->dialect->jsonPath($qualified, $path->json);
    }

    /** @param list<string> $names relation names, already deduplicated and in order */
    private function joins(Entity $entity, array $names): string
    {
        $sql = '';

        foreach ($names as $name) {
            $relation = $entity->relation($name);

            $sql .= ' ' . $relation->type->keyword()
                . ' ' . $this->dialect->quoteIdentifier($relation->table)
                . ' ON ' . $relation->on;
        }

        return $sql;
    }
}
