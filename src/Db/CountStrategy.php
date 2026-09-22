<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * How hard to work for the total row count.
 *
 * COUNT(*) over ten million rows is usually the most expensive thing on a
 * page, and a pager only needs it to draw the last page number — so this is a
 * choice a project makes per grid, not a fixed cost.
 */
enum CountStrategy: string
{
    /** One COUNT(*) over the same conditions. Correct, and the default. */
    case Exact = 'exact';

    /** Not yet available: it needs a cache store, which arrives in milestone 10. */
    case Cached = 'cached';

    /** From table metadata — cheap, approximate, and only valid unfiltered. */
    case Estimate = 'estimate';

    /** No count at all: the pager offers next and previous, not a last page. */
    case None = 'none';
}
