<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Reads rows for a query.
 *
 * The seam a project replaces when it wants its own reader — an ORM, a search
 * index, a remote API. The core only ever asks for "the rows matching this
 * description", which is why the replacement does not have to speak SQL.
 */
interface RowSource
{
    public function fetch(Query $query): Result;
}
