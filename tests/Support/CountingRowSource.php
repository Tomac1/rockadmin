<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;

/**
 * Wraps a real `RowSource` to count how often it is asked and to keep the
 * last `Result` it produced, so an integration test can assert "one page,
 * one call" and inspect the statements a real driver actually issued without
 * `ListView` itself having to carry them.
 */
final class CountingRowSource implements RowSource
{
    public int $calls = 0;

    public ?Result $lastResult = null;

    public function __construct(private readonly RowSource $inner)
    {
    }

    public function fetch(Query $query): Result
    {
        $this->calls++;

        return $this->lastResult = $this->inner->fetch($query);
    }
}
