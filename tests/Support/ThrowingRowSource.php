<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Db\DbException;
use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;

/**
 * A `RowSource` that always fails with a given `DbException`, so a unit
 * test can prove what a caller does with a particular SQLSTATE without
 * opening a real connection -- see `PreviewRegionTest`'s coverage of
 * `PreviewRegion`'s own SQLSTATE-class-'22'-means-no-such-row rule.
 */
final class ThrowingRowSource implements RowSource
{
    public function __construct(private readonly DbException $exception)
    {
    }

    public function fetch(Query $query): Result
    {
        throw $this->exception;
    }
}
