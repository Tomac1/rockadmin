<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * What a read produced, and how.
 *
 * The statements travel with the rows so the development console can show
 * exactly what ran, which is the spec's requirement that a developer can see
 * how a grid's queries were composed.
 */
final class Result
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param ?int                       $total null when no count was asked for
     * @param list<Sql>                  $statements in the order they ran
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?int $total,
        public readonly array $statements,
    ) {
    }
}
