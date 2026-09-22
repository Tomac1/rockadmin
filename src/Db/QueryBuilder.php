<?php

declare(strict_types=1);

namespace RockAdmin\Db;

use RockAdmin\Config\Placeholder;

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
            throw new DbException("A query on '{$query->entity->table}' needs at least one column.");
        }

        $selects = [];
        $bindings = [];
        $joins = [];

        $expressions = $this->columnExpressions($query, $joins);

        foreach ($expressions as $alias => $expression) {
            $bindings = [...$bindings, ...$expression->bindings];
            $selects[] = $expression->text . ' AS ' . $this->dialect->quoteIdentifier($alias);
        }

        $where = $this->where($query, $expressions);
        $conditions = $where->text === '' ? [] : [$where->text];
        $bindings = [...$bindings, ...$where->bindings];

        if ($query->page?->isKeyset() === true && $query->page->after !== null) {
            $conditions[] = $this->dialect->qualify($query->entity->table, $query->entity->key) . ' < ?';
            $bindings[] = $query->page->after;
        }

        $order = $this->order($query, $expressions);

        $text = 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $this->dialect->quoteIdentifier($query->entity->table)
            . $this->joins($query->entity, $this->joinNames($joins))
            . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . $order->text
            . $this->limit($query->page);

        return new Sql($text, [...$bindings, ...$order->bindings]);
    }

    /**
     * Scope first, then filters, then search — all joined with AND.
     *
     * Scope is applied to the entity's own table and is never reachable from a
     * filter, because it is the boundary a workspace draws. A filter naming a
     * column the page did not select is dropped: filters arrive from a URL.
     *
     * @param array<string, Sql> $expressions alias => SQL expression
     */
    private function where(Query $query, array $expressions): Sql
    {
        $conditions = [];
        $bindings = [];

        foreach ($query->scope as $column => $value) {
            $this->assertBound($value, $column);

            $conditions[] = $this->dialect->qualify($query->entity->table, $column) . ' = ?';
            $bindings[] = $value;
        }

        foreach ($query->filters as $filter) {
            if (!isset($expressions[$filter->column])) {
                continue;
            }

            $condition = $this->condition($expressions[$filter->column], $filter);
            $conditions[] = $condition->text;
            $bindings = [...$bindings, ...$condition->bindings];
        }

        if ($query->search !== null && $query->search->term !== '') {
            $alternatives = [];

            foreach ($query->search->columns as $alias) {
                if (!isset($expressions[$alias])) {
                    continue;
                }

                $expression = $expressions[$alias];

                $alternatives[] = $expression->text . ' ' . $this->dialect->caseInsensitiveLike() . ' ?';
                // These bindings are pushed here, inside the loop, while the group
                // they belong to is appended below. That is only correct because
                // search is the last clause of this WHERE: anything appended after
                // it -- a raw condition, say -- must push its bindings after these,
                // or text and values stop lining up.
                $bindings = [...$bindings, ...$expression->bindings];
                $bindings[] = '%' . $this->escapeLike($query->search->term) . '%';
            }

            if ($alternatives !== []) {
                $conditions[] = '(' . implode(' OR ', $alternatives) . ')';
            }
        }

        return new Sql(implode(' AND ', $conditions), $bindings);
    }

    /**
     * One filter, with the expression's own bindings spliced in ahead of the
     * condition's.
     *
     * A JSON source resolves to text holding a placeholder, and that
     * placeholder comes first in the text, so its value comes first too.
     */
    private function condition(Sql $expression, Filter $filter): Sql
    {
        if ($filter->operator === FilterOperator::In && $filter->value === []) {
            // "IN ()" is a syntax error, and dropping the condition would make
            // an empty selection match every row -- the opposite of the ask.
            // The expression is discarded along with it, so its bindings are
            // not spliced in: there is no placeholder left standing for them.
            return new Sql('1 = 0');
        }

        $condition = $this->operator($expression->text, $filter);

        return new Sql($condition->text, [...$expression->bindings, ...$condition->bindings]);
    }

    private function operator(string $expression, Filter $filter): Sql
    {
        $like = $this->dialect->caseInsensitiveLike();

        return match ($filter->operator) {
            FilterOperator::Equals => new Sql("{$expression} = ?", [$filter->value]),
            FilterOperator::NotEquals => new Sql("{$expression} <> ?", [$filter->value]),
            FilterOperator::GreaterThan => new Sql("{$expression} > ?", [$filter->value]),
            FilterOperator::GreaterOrEqual => new Sql("{$expression} >= ?", [$filter->value]),
            FilterOperator::LessThan => new Sql("{$expression} < ?", [$filter->value]),
            FilterOperator::LessOrEqual => new Sql("{$expression} <= ?", [$filter->value]),
            FilterOperator::Contains => new Sql(
                "{$expression} {$like} ?",
                ['%' . $this->escapeLike($this->text($filter)) . '%'],
            ),
            FilterOperator::StartsWith => new Sql(
                "{$expression} {$like} ?",
                [$this->escapeLike($this->text($filter)) . '%'],
            ),
            FilterOperator::EndsWith => new Sql(
                "{$expression} {$like} ?",
                ['%' . $this->escapeLike($this->text($filter))],
            ),
            FilterOperator::IsNull => new Sql("{$expression} IS NULL"),
            FilterOperator::IsNotNull => new Sql("{$expression} IS NOT NULL"),
            FilterOperator::Between => $this->between($expression, $filter),
            FilterOperator::In => $this->in($expression, $filter),
        };
    }

    private function between(string $expression, Filter $filter): Sql
    {
        if (!\is_array($filter->value) || \count($filter->value) !== 2) {
            throw new DbException(
                "A 'between' filter on '{$filter->column}' needs exactly two values.",
            );
        }

        return new Sql("{$expression} BETWEEN ? AND ?", array_values($filter->value));
    }

    private function in(string $expression, Filter $filter): Sql
    {
        if (!\is_array($filter->value)) {
            throw new DbException("An 'in' filter on '{$filter->column}' needs a list of values.");
        }

        $values = array_values($filter->value);

        if ($values === []) {
            // condition() answers an empty list before the expression is spent,
            // so this arm is only reached if that guard is ever moved.
            return new Sql('1 = 0');
        }

        return new Sql(
            $expression . ' IN (' . implode(', ', array_fill(0, \count($values), '?')) . ')',
            $values,
        );
    }

    private function text(Filter $filter): string
    {
        if (!\is_scalar($filter->value)) {
            throw new DbException(
                "A text filter on '{$filter->column}' needs a scalar, got "
                . get_debug_type($filter->value) . '.',
            );
        }

        return (string) $filter->value;
    }

    /** Escapes the wildcards LIKE understands, so a term matches literally. */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    private function assertBound(mixed $value, string $column): void
    {
        if ($value instanceof Placeholder) {
            throw new DbException(
                "The scope on '{$column}' is still {$value}. A workspace or user placeholder "
                . 'must be bound to the request before it reaches a query, so that it arrives '
                . 'as a value rather than as text.',
            );
        }
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

    /**
     * Keyset paging orders by the key and nothing else: a cursor is only
     * meaningful against the order it was taken from, so honouring another
     * sort alongside it would silently skip rows.
     *
     * It answers with an Sql rather than a bare string because sorting on a
     * JSON column puts a placeholder into ORDER BY, and the pointer it stands
     * for has to travel with it.
     *
     * @param array<string, Sql> $expressions alias => SQL expression
     */
    private function order(Query $query, array $expressions): Sql
    {
        if ($query->page?->isKeyset() === true) {
            return new Sql(
                ' ORDER BY '
                . $this->dialect->qualify($query->entity->table, $query->entity->key) . ' DESC',
            );
        }

        $parts = [];
        $bindings = [];

        foreach ($query->sort as $sort) {
            if (!isset($expressions[$sort->column])) {
                continue;
            }

            $expression = $expressions[$sort->column];

            $parts[] = $expression->text . ' ' . $sort->direction->keyword();
            $bindings = [...$bindings, ...$expression->bindings];
        }

        return $parts === []
            ? new Sql('')
            : new Sql(' ORDER BY ' . implode(', ', $parts), $bindings);
    }

    /**
     * The limit is interpolated rather than bound, which is safe because both
     * values are integers this class produced — and necessary, because several
     * databases refuse a placeholder in LIMIT.
     */
    private function limit(?Page $page): string
    {
        if ($page === null) {
            return '';
        }

        return " LIMIT {$page->limit}" . ($page->offset > 0 ? " OFFSET {$page->offset}" : '');
    }

    /**
     * The statement that yields the total, or null when none was asked for.
     *
     * An estimate reads table metadata, which knows nothing about a WHERE, so
     * a query that narrows its result falls back to an exact count rather than
     * reporting the size of the whole table.
     */
    public function count(Query $query): ?Sql
    {
        if ($query->count === CountStrategy::None) {
            return null;
        }

        if ($query->count === CountStrategy::Cached) {
            throw new DbException(
                'A cached count needs a cache store, which arrives in milestone 10. '
                . "Use 'exact', 'estimate' or 'none' until then.",
            );
        }

        if ($query->count === CountStrategy::Estimate && !$this->isNarrowed($query)) {
            return $this->dialect->estimatedCount($query->entity->table);
        }

        $joins = [];
        $expressions = $this->columnExpressions($query, $joins);
        $where = $this->where($query, $expressions);

        // Every column keeps its expression, because a filter may name any alias
        // the page selected. Only the joins narrow: a count selects nothing, so a
        // relation no condition mentions is wasted work at best, and a LEFT JOIN
        // to a to-many relation would multiply rows and inflate COUNT(*) past the
        // number of rows the grid shows. Scope needs no join either -- it is
        // applied to the entity's own table.
        $names = $this->joinNames($joins, $this->conditionAliases($query, $expressions));

        return new Sql(
            'SELECT COUNT(*) FROM ' . $this->dialect->quoteIdentifier($query->entity->table)
            . $this->joins($query->entity, $names)
            . ($where->text === '' ? '' : ' WHERE ' . $where->text),
            $where->bindings,
        );
    }

    private function isNarrowed(Query $query): bool
    {
        return $query->scope !== []
            || $query->filters !== []
            || ($query->search !== null && $query->search->term !== '');
    }

    /**
     * The aliases this query's conditions actually reference.
     *
     * @param  array<string, Sql> $expressions alias => SQL expression
     * @return list<string>
     */
    private function conditionAliases(Query $query, array $expressions): array
    {
        $aliases = [];

        foreach ($query->filters as $filter) {
            if (isset($expressions[$filter->column])) {
                $aliases[$filter->column] = true;
            }
        }

        if ($query->search !== null && $query->search->term !== '') {
            foreach ($query->search->columns as $alias) {
                if (isset($expressions[$alias])) {
                    $aliases[$alias] = true;
                }
            }
        }

        return array_keys($aliases);
    }

    /**
     * alias => SQL expression, plus the relations each alias needs.
     *
     * The whole expression is returned, not its text: a JSON source resolves to
     * text holding a placeholder, so a WHERE or an ORDER BY that reuses that
     * text has to re-supply the pointer it stands for.
     *
     * @param  array<string, list<string>> $joins collected by reference, alias => relation paths
     * @return array<string, Sql>
     */
    private function columnExpressions(Query $query, array &$joins): array
    {
        $expressions = [];
        $joins = [];

        foreach ($query->columns as $alias => $source) {
            $path = SourcePath::parse($source);

            $joins[(string) $alias] = $path->joins();
            $expressions[(string) $alias] = $this->expression($query->entity, $path);
        }

        return $expressions;
    }

    /**
     * The relations to join, deduplicated and outermost first.
     *
     * @param  array<string, list<string>> $joins   alias => relation paths
     * @param  ?list<string>               $aliases the aliases to consider, or null for every one
     * @return list<string>
     */
    private function joinNames(array $joins, ?array $aliases = null): array
    {
        $names = [];

        foreach ($joins as $alias => $paths) {
            if ($aliases !== null && !\in_array($alias, $aliases, true)) {
                continue;
            }

            foreach ($paths as $path) {
                $names[$path] = true;
            }
        }

        return array_keys($names);
    }
}
