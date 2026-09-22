<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Everything a grid asks the database for.
 *
 * A description, not a builder: it holds what was asked and knows nothing
 * about SQL. That is what lets the same description be answered by the
 * default SQL reader or by a project's own RowSource.
 */
final class Query
{
    /**
     * @param array<string, string> $columns alias => source path
     * @param array<string, mixed>  $scope   column of the entity's own table => value
     * @param list<Filter>          $filters
     * @param list<Sort>            $sort
     * @param list<Collection>      $collections
     */
    public function __construct(
        public readonly Entity $entity,
        public readonly array $columns,
        public readonly array $scope = [],
        public readonly array $filters = [],
        public readonly ?Search $search = null,
        public readonly array $sort = [],
        public readonly ?Page $page = null,
        public readonly CountStrategy $count = CountStrategy::Exact,
        public readonly array $collections = [],
    ) {
        foreach ($collections as $collection) {
            // A collection is written onto each row under its alias. Using the
            // entity's key for that overwrites the key on every row, so a second
            // collection would find nothing to group by and attach empty lists
            // to a grid that looks like it is working.
            //
            // Collection cannot refuse this itself -- it is told an alias, a
            // table and two columns, never which entity it hangs off -- so the
            // refusal lives here, where both names are in hand and every
            // RowSource, not just the SQL one, is covered by it.
            if ($collection->alias === $entity->key) {
                throw new DbException(
                    "The collection '{$collection->alias}' is named after the key of "
                    . "'{$entity->table}'. Attaching it would overwrite that key on every row, "
                    . 'so give the collection a name of its own.',
                );
            }
        }
    }
}
