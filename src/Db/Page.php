<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Which slice of the result to read.
 *
 * Offset paging is what a numbered pager needs. Keyset paging walks backwards
 * from a cursor and stays fast on page 100 000, where an offset makes the
 * database count past everything before it — but it can only offer "next",
 * which is why both exist.
 */
final class Page
{
    private function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly mixed $after,
        private readonly bool $keyset,
    ) {
    }

    public static function of(int $number, int $perPage): self
    {
        self::assertPerPage($perPage);

        $number = max(1, $number);

        return new self($perPage, ($number - 1) * $perPage, null, false);
    }

    /** @param mixed $key the last key of the previous page, or null to start */
    public static function after(mixed $key, int $perPage): self
    {
        self::assertPerPage($perPage);

        return new self($perPage, 0, $key, true);
    }

    public function isKeyset(): bool
    {
        return $this->keyset;
    }

    private static function assertPerPage(int $perPage): void
    {
        if ($perPage < 1) {
            throw new DbException("A page needs at least one row, got {$perPage}.");
        }
    }
}
