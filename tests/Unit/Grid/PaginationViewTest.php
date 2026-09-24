<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Grid\PaginationView;

/**
 * The pager's arithmetic, isolated from everything that assembles a grid
 * around it — this is where a pager most often goes wrong: an exact multiple
 * producing a spurious empty last page, a total of zero producing no pages at
 * all, or a bookmarked page past the end producing an empty grid instead of
 * the real last page.
 */
#[CoversClass(PaginationView::class)]
final class PaginationViewTest extends TestCase
{
    private function urlForPage(): \Closure
    {
        return static fn (int $number): string => "/r/ads/grid?page={$number}";
    }

    public function testThePageCountIsTheTotalDividedByTheLimitRoundedUp(): void
    {
        $pagination = PaginationView::of(1, 25, 51, 25, $this->urlForPage());

        $this->assertSame(3, $pagination->pageCount);
    }

    public function testAnExactMultipleDoesNotProduceAnEmptyLastPage(): void
    {
        // 50 rows at 25 per page is two pages, not three.
        $pagination = PaginationView::of(1, 25, 50, 25, $this->urlForPage());

        $this->assertSame(2, $pagination->pageCount);
    }

    public function testATotalOfZeroIsOnePageNotZero(): void
    {
        // An empty grid is still a page, not the absence of one.
        $pagination = PaginationView::of(1, 25, 0, 0, $this->urlForPage());

        $this->assertSame(1, $pagination->pageCount);
        $this->assertSame(1, $pagination->currentPage);
        $this->assertFalse($pagination->hasNext);
        $this->assertFalse($pagination->hasPrevious);
    }

    public function testAPageBeyondTheEndShowsTheLastPage(): void
    {
        // Somebody bookmarked page nine and three rows were deleted since —
        // 50 rows at 25 per page is only two pages.
        $pagination = PaginationView::of(9, 25, 50, 0, $this->urlForPage());

        $this->assertSame(2, $pagination->currentPage);
        $this->assertSame(2, $pagination->pageCount);
        $this->assertTrue($pagination->hasPrevious);
        $this->assertFalse($pagination->hasNext);
    }

    public function testAnUnknownTotalStillOffersNextAndPrevious(): void
    {
        // No count strategy ran: no page count is invented, but a full page
        // of rows is still an honest signal that another page might follow.
        $full = PaginationView::of(2, 25, null, 25, $this->urlForPage());

        $this->assertNull($full->pageCount);
        $this->assertSame([], $full->pages);
        $this->assertTrue($full->hasPrevious);
        $this->assertTrue($full->hasNext);
        $this->assertNotNull($full->previousUrl);
        $this->assertNotNull($full->nextUrl);

        $partial = PaginationView::of(2, 25, null, 10, $this->urlForPage());

        $this->assertFalse($partial->hasNext, 'a partial page is the last one');
    }

    public function testTheWindowOfPageNumbersIsCenteredOnTheCurrentPage(): void
    {
        $pagination = PaginationView::of(5, 10, 100, 10, $this->urlForPage());

        $numbers = array_column($pagination->pages, 'number');
        $this->assertSame([3, 4, 5, 6, 7], $numbers, 'two pages on either side of the current one');

        $current = array_column($pagination->pages, 'current', 'number');
        $this->assertTrue($current[5]);
        $this->assertFalse($current[3]);
        $this->assertFalse($current[7]);
    }

    public function testTheWindowIsClampedAtTheFirstPage(): void
    {
        $pagination = PaginationView::of(1, 10, 100, 10, $this->urlForPage());

        $this->assertSame([1, 2, 3], array_column($pagination->pages, 'number'));
    }

    public function testTheWindowIsClampedAtTheLastPage(): void
    {
        $pagination = PaginationView::of(10, 10, 100, 10, $this->urlForPage());

        $this->assertSame([8, 9, 10], array_column($pagination->pages, 'number'));
    }

    public function testAPageNumberBelowOneIsTreatedAsOne(): void
    {
        $pagination = PaginationView::of(0, 25, 50, 0, $this->urlForPage());

        $this->assertSame(1, $pagination->currentPage);
    }
}
