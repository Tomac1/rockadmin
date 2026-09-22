<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * A one-to-many read in one supplementary query for the whole page.
 *
 * Joining it into the main query would multiply the rows; reading it per row
 * would be the N+1 this layer exists to prevent. So it is fetched once for
 * every key on the page and grouped in PHP.
 */
final class Collection
{
    /**
     * @param string $alias      the key the collected values appear under on each row
     * @param string $table      the child table
     * @param string $foreignKey the child column pointing at the parent's key
     * @param string $column     the child column to collect
     */
    public function __construct(
        public readonly string $alias,
        public readonly string $table,
        public readonly string $foreignKey,
        public readonly string $column,
    ) {
    }
}
