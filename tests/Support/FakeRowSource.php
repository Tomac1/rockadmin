<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;

/**
 * A `RowSource` answering from a fixed queue of results, so a unit test can
 * assemble a `ListView` without a database.
 *
 * `$calls` and `$queries` let a test assert on how the fetcher was used —
 * once per page, never once per row — without caring what SQL, if any, ran.
 */
final class FakeRowSource implements RowSource
{
    public int $calls = 0;

    /** @var list<Query> */
    public array $queries = [];

    /** @param list<Result> $results answered in order; the last one repeats once exhausted */
    public function __construct(private readonly array $results)
    {
    }

    public function fetch(Query $query): Result
    {
        $this->calls++;
        $this->queries[] = $query;

        $index = \count($this->queries) - 1;

        return $this->results[$index] ?? $this->results[\count($this->results) - 1];
    }
}
