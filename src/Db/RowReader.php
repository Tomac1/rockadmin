<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * One row, by its key.
 *
 * `FormRegion` has wanted this since milestone 7 and `ActionHandler` wants it
 * now, for different reasons that produce the same query: a form writes over
 * the row it loaded, and a configured action re-checks the `when` conditions
 * that decided whether it was offered. Two copies of a single-row read would
 * be two copies of the SQLSTATE reading below, and that is the half nobody
 * would notice had drifted until a PostgreSQL installation started answering
 * 500 where MySQL answered 404.
 *
 * It builds a `Query` and hands it to a `RowSource`, which is what rule 4
 * requires of anything above the database: a `Query` is a value, and the one
 * thing that turns it into SQL is `QueryBuilder`.
 */
final class RowReader
{
    public function __construct(private readonly RowSource $rows)
    {
    }

    /**
     * The row, or null when the key names none.
     *
     * The entity's key is always selected whether or not the caller asked for
     * it, because the filter matches against it — `QueryBuilder` drops a
     * filter naming an alias the query never selected, and a read that
     * silently ignored its own id would return whichever row the database
     * happened to put first.
     *
     * @param  list<string>              $columns the columns wanted, besides the key
     * @param  array<string, mixed>      $scope   the page scope, applied exactly as a grid applies it
     * @return array<string, mixed>|null
     */
    public function byKey(Entity $entity, string $key, array $columns = [], array $scope = []): ?array
    {
        $selected = [$entity->key => $entity->key];

        foreach ($columns as $column) {
            $selected[$column] = $column;
        }

        $query = new Query(
            entity: $entity,
            columns: $selected,
            scope: $scope,
            filters: [new Filter($entity->key, FilterOperator::Equals, $key)],
            page: Page::of(1, 1),
            count: CountStrategy::None,
        );

        return $this->fetch($query)?->rows[0] ?? null;
    }

    /**
     * Runs it, reading a failed statement's own SQLSTATE to tell "the id does
     * not name a row" from "something is actually broken".
     *
     * `/p/ads/abc/edit` filters an integer key with a value that is not one.
     * MySQL coerces it and matches nothing; PostgreSQL refuses the statement
     * with SQLSTATE class '22', a data exception. Both are saying no such row,
     * so both answer null. Anything else keeps propagating: a database that is
     * actually failing must not read as a row that does not exist.
     */
    private function fetch(Query $query): ?Result
    {
        try {
            return $this->rows->fetch($query);
        } catch (DbException $e) {
            if ($e->sqlStateClass() === '22') {
                return null;
            }

            throw $e;
        }
    }
}
