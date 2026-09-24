<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use Closure;

/**
 * A pager that does not lie.
 *
 * With an exact count, `$pageCount` is a number and the requested page is
 * clamped into range — a bookmark to page nine survives three rows being
 * deleted since by landing on the real last page, not an empty grid. With no
 * count at all, `$pageCount` stays null and the pager offers only "previous"
 * and "next", built from whether this page came back full, rather than
 * inventing a page count it cannot know.
 */
final class PaginationView
{
    /**
     * How many page numbers are shown on either side of the current one. Two
     * on each side is enough to orient without crowding a mobile-width pager.
     */
    private const int WINDOW_RADIUS = 2;

    /** @param list<array{number: int, url: string, current: bool}> $pages empty when the total is unknown */
    private function __construct(
        public readonly int $currentPage,
        public readonly ?int $total,
        public readonly ?int $pageCount,
        public readonly array $pages,
        public readonly bool $hasPrevious,
        public readonly bool $hasNext,
        public readonly ?string $previousUrl,
        public readonly ?string $nextUrl,
    ) {
    }

    /**
     * @param int                   $page       the page that was asked for, 1 or more
     * @param int                   $perPage
     * @param ?int                  $total      null when no count strategy ran
     * @param int                   $rowCount   rows actually returned for this page
     * @param Closure(int): string  $urlForPage
     */
    public static function of(int $page, int $perPage, ?int $total, int $rowCount, Closure $urlForPage): self
    {
        $perPage = max(1, $perPage);
        $requested = max(1, $page);

        // A total of zero is still one page — an empty grid is a page, not the
        // absence of one — and ceil() alone already stops an exact multiple
        // (50 rows at 25 per page) from producing a spurious empty last page.
        $pageCount = $total !== null ? max(1, (int) ceil($total / $perPage)) : null;

        $currentPage = $pageCount !== null ? min($requested, $pageCount) : $requested;

        $hasPrevious = $currentPage > 1;
        // With no total, "this page came back full" is the only honest signal
        // that another one might follow; a partial page is the last one.
        $hasNext = $pageCount !== null ? $currentPage < $pageCount : $rowCount >= $perPage;

        return new self(
            currentPage: $currentPage,
            total: $total,
            pageCount: $pageCount,
            pages: $pageCount === null ? [] : self::window($currentPage, $pageCount, $urlForPage),
            hasPrevious: $hasPrevious,
            hasNext: $hasNext,
            previousUrl: $hasPrevious ? $urlForPage($currentPage - 1) : null,
            nextUrl: $hasNext ? $urlForPage($currentPage + 1) : null,
        );
    }

    /**
     * @param  Closure(int): string                              $urlForPage
     * @return list<array{number: int, url: string, current: bool}>
     */
    private static function window(int $currentPage, int $pageCount, Closure $urlForPage): array
    {
        $start = max(1, $currentPage - self::WINDOW_RADIUS);
        $end = min($pageCount, $currentPage + self::WINDOW_RADIUS);

        $pages = [];

        for ($number = $start; $number <= $end; $number++) {
            $pages[] = [
                'number' => $number,
                'url' => $urlForPage($number),
                'current' => $number === $currentPage,
            ];
        }

        return $pages;
    }
}
