<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Identity;
use RockAdmin\Config\RegionReference;
use RockAdmin\Grid\ActionBuilder;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\ActionDefinition;
use RockAdmin\Page\ActionType;
use RockAdmin\Tests\Support\Access;
use RockAdmin\View\ActionView;

/**
 * An `open` control, as `ActionBuilder` draws it.
 *
 * Two URLs, and the one that matters is the one script never uses. An `open`
 * is an anchor that script upgrades, not a button that script replaces: 8.6's
 * own example is a button, and a button does nothing at all with JavaScript
 * off. So `href` is the standalone page showing the same thing, and
 * `data-ra-open` is the fragment.
 *
 * The rest is the guard milestone 8 wrote for `to`, applied to `params`,
 * which is the second place a row's value reaches a URL.
 */
#[CoversClass(ActionBuilder::class)]
#[CoversClass(ActionView::class)]
final class OpenActionViewTest extends TestCase
{
    public function testTheFragmentUrlIsTheRegionRoute(): void
    {
        $view = $this->open();

        $this->assertSame('/admin/r/ads/detail?id=42', $view->fragmentUrl);
    }

    public function testTheHrefIsTheStandalonePageForThatRow(): void
    {
        // What the control does with no script, and it has to address the
        // same thing the overlay would show. A link to the fragment would
        // land somebody on an unstyled page with no navigation.
        $view = $this->open();

        $this->assertSame('/admin/p/ads/42', $view->url);
    }

    public function testAnOpenWithNoIdAddressesThePageItself(): void
    {
        // Decided the way `RegionHandler` decides which row a preview is
        // about: by `id` in the query. One convention read in two places,
        // rather than a second rule here about what a region "is".
        $view = $this->open(params: []);

        $this->assertSame('/admin/p/ads', $view->url);
        $this->assertSame('/admin/r/ads/detail', $view->fragmentUrl);
    }

    public function testARegionOfAnotherPageAddressesThatPage(): void
    {
        // Both URLs. The fragment loads under the users page's permissions,
        // and the no-script fallback lands on the users page -- not on the
        // ads page the control was drawn on.
        $view = $this->open(
            reference: new RegionReference('detail', 'users'),
            params: ['id' => '{user_id}'],
            row: ['id' => 42, 'user_id' => 7],
        );

        $this->assertSame('/admin/r/users/detail?id=7', $view->fragmentUrl);
        $this->assertSame('/admin/p/users/7', $view->url);
    }

    public function testTheTitleIsSubstitutedFromTheRow(): void
    {
        $view = $this->open(title: 'Ad #{id}');

        $this->assertSame('Ad #42', $view->title);
    }

    public function testTheTitleKeepsCharactersAUrlWouldRefuse(): void
    {
        // A heading is escaped into text, never parsed as a route, so the
        // path refusals do not apply to it. Refusing a slash here would be
        // refusing data for the shape of a URL it never becomes.
        $view = $this->open(
            title: '{title}',
            params: [],
            row: ['id' => 42, 'title' => 'Kolo / bazar . 2026'],
        );

        $this->assertSame('Kolo / bazar . 2026', $view->title);
    }

    public function testAnOpenIsNeverAPostAndCarriesNoToken(): void
    {
        // It shows something and changes nothing. A token on it would say
        // otherwise, and a confirmation in front of it is refused at load.
        $view = $this->open();

        $this->assertFalse($view->isPost);
        $this->assertSame('', $view->token);
        $this->assertSame('', $view->confirm);
        $this->assertNull($view->rowKey);
        $this->assertTrue($view->isOpen());
    }

    public function testTheOverlayAndItsWidthReachTheView(): void
    {
        $view = $this->open(in: 'modal', size: 'xl');

        $this->assertSame('modal', $view->in);
        $this->assertSame('xl', $view->size);
    }

    // --- the guard milestone 8 wrote for `to`, applied to `params` ---

    /** @return iterable<string, array{mixed}> */
    public static function dangerousValues(): iterable
    {
        yield 'a path traversal' => ['../../etc/passwd'];
        yield 'a query separator' => ['7?admin=1'];
        yield 'a fragment' => ['7#x'];
        yield 'a space' => ['7 8'];
        yield 'a dot segment' => ['..'];
    }

    #[DataProvider('dangerousValues')]
    public function testAParamStillAddressesTheRowItWasDrawnFor(mixed $value): void
    {
        // The same test milestone 8 wrote for a row placeholder in `to`.
        // `params` is the second place a row's own value reaches a URL, and
        // the first one was guarded by code this one has to share rather than
        // copy — two spellings of "substitute a row placeholder" is how one
        // of them comes to miss the guard the other has.
        try {
            $view = $this->open(params: ['id' => '{key}'], row: ['id' => 42, 'key' => $value]);
        } catch (InvalidArgumentException $e) {
            // Refused outright is the other acceptable answer, and for `/`
            // and `..` it is the only one. What is asserted then is that the
            // refusal names the column, because the alternative is somebody
            // meeting it with no idea which row or which value caused it.
            $this->assertStringContainsString("'key'", $e->getMessage());

            return;
        }

        $this->assertStringStartsWith('/admin/r/ads/detail?', $view->fragmentUrl);
        $this->assertStringNotContainsString('admin=1', $view->fragmentUrl);
        $this->assertSame(
            1,
            substr_count($view->fragmentUrl, '?'),
            'A parameter introduced a second query separator, so it is no longer one parameter.',
        );
    }

    public function testAConfigurationPlaceholderInAParamIsRefused(): void
    {
        // `{{user.id}}` is the logged-in administrator on every row, and
        // writing it where `{user_id}` was meant looks correct in testing,
        // because the developer is usually looking at their own records.
        $this->expectException(InvalidArgumentException::class);

        $this->open(params: ['id' => '{{user.id}}']);
    }

    public function testAParamNamingAColumnTheRowHasNotGotIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->open(params: ['id' => '{nothing}']);
    }

    // --- harness ---

    /**
     * @param array<string, string> $params
     * @param array<string, mixed>  $row
     */
    private function open(
        ?RegionReference $reference = null,
        array $params = ['id' => '{id}'],
        string $in = 'offcanvas',
        string $size = 'lg',
        string $title = '',
        array $row = ['id' => 42],
    ): ActionView {
        $action = new ActionDefinition(
            key: 'peek',
            label: 'Preview',
            type: ActionType::Open,
            to: $reference ?? new RegionReference('detail'),
            in: $in,
            size: $size,
            title: $title,
            params: $params,
        );

        $views = $this->builder()->forRow('ads', [$action], $row, 'id');

        $this->assertCount(1, $views, 'The action was not drawn at all.');

        return $views[0];
    }

    private function builder(): ActionBuilder
    {
        return new ActionBuilder(
            Access::gate([]),
            $this->identity(),
            new UrlGenerator('/admin'),
            new Csrf(new ArraySessionStore()),
        );
    }

    private function identity(): Identity
    {
        return Access::identity();
    }
}
