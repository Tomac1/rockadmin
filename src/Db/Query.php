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
    }
}
