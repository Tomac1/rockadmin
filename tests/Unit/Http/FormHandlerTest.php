<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Result;
use RockAdmin\Db\Sql;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\FakeRowSource;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * `FormHandler` serves the three GET routes that draw a form:
 * `p/{page}/create`, `p/{page}/{id}/edit` and `p/{page}/{id}/copy`. What a
 * form looks like is `FormRegion`'s job and is tested there; these tests fix
 * its `RowSource` and assert on the document this class builds around it,
 * plus the decisions only it makes: which route means which form, what is a
 * 404, and what happens to `_ret`.
 */
#[CoversClass(FormHandler::class)]
final class FormHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-form-handler-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }

    private function writePage(string $name, string $body): void
    {
        file_put_contents($this->root . '/pages/' . $name . '.php', "<?php\n\nreturn {$body};\n");
    }

    private function writeAdsPageWithForm(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => [
                                'title' => ['type' => 'text', 'default' => 'Untitled'],
                                'state' => ['type' => 'text', 'default' => 'draft'],
                            ],
                            'copy' => ['reset' => ['state']],
                        ],
                    ],
                ],
            ]
            PHP);
    }

    private function writeAdsPageWithoutForm(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                ],
            ]
            PHP);
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function handler(array $rows = [['id' => 1, 'title' => 'Horské kolo', 'state' => 'live']]): FormHandler
    {
        $urls = new UrlGenerator('/admin');

        return new FormHandler(
            $this->repository(),
            new FormRegion(
                new FakeRowSource([new Result($rows, null, [new Sql('SELECT 1')])]),
                $urls,
                new Csrf(new ArraySessionStore()),
            ),
            new Renderer(new TemplateResolver([]), new Escaper(), $urls),
            new Assets($urls),
            new FlashBag(new ArraySessionStore()),
            $urls,
            'RockAdmin Demo',
            'auto',
            [new MenuItemView('Ads', '/admin/p/ads', key: 'ads')],
        );
    }

    public function testACreateFormRendersAsAWholeDocument(): void
    {
        $this->writeAdsPageWithForm();

        $response = $this->handler()->handle(
            new Route('page.create', ['page' => 'ads']),
            new Request('GET', 'p/ads/create'),
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<!doctype html>', $response->body);
        $this->assertStringContainsString('action="/admin/a/ads/create"', $response->body);
        $this->assertStringContainsString('value="Untitled"', $response->body);
    }

    public function testAnEditFormDrawsTheStoredRowAndItsKey(): void
    {
        $this->writeAdsPageWithForm();

        $response = $this->handler()->handle(
            new Route('page.edit', ['page' => 'ads', 'id' => '1']),
            new Request('GET', 'p/ads/1/edit'),
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('action="/admin/a/ads/update"', $response->body);
        $this->assertStringContainsString('Horské kolo', $response->body);
        $this->assertStringContainsString('name="' . FormFields::ID . '" value="1"', $response->body);
    }

    public function testACopyFormResetsWhatCopyResetNamesAndCarriesNoKey(): void
    {
        $this->writeAdsPageWithForm();

        $response = $this->handler()->handle(
            new Route('page.copy', ['page' => 'ads', 'id' => '1']),
            new Request('GET', 'p/ads/1/copy'),
        );

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('action="/admin/a/ads/create"', $response->body);
        $this->assertStringContainsString('Horské kolo', $response->body);
        $this->assertStringContainsString('value="draft"', $response->body);
        $this->assertStringNotContainsString('name="' . FormFields::ID . '"', $response->body);
    }

    public function testAnInternalReturnAddressTravelsIntoTheForm(): void
    {
        $this->writeAdsPageWithForm();

        $response = $this->handler()->handle(
            new Route('page.create', ['page' => 'ads']),
            new Request('GET', 'p/ads/create', [FormFields::RETURN_TO => '/admin/p/ads?grid%5Bpage%5D=3']),
        );

        $this->assertStringContainsString(
            'name="' . FormFields::RETURN_TO . '" value="/admin/p/ads?grid%5Bpage%5D=3"',
            $response->body,
        );
    }

    public function testAHostileReturnAddressFallsBackToThePagesIndexRatherThanTravelling(): void
    {
        $this->writeAdsPageWithForm();

        $response = $this->handler()->handle(
            new Route('page.create', ['page' => 'ads']),
            new Request('GET', 'p/ads/create', [FormFields::RETURN_TO => 'https://evil.com/']),
        );

        $this->assertStringNotContainsString('evil.com', $response->body);
        $this->assertStringContainsString(
            'name="' . FormFields::RETURN_TO . '" value="/admin/p/ads"',
            $response->body,
        );
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(
            new Route('page.create', ['page' => 'nope']),
            new Request('GET', 'p/nope/create'),
        );
    }

    public function testAPageWithNoFormRegionIsNotFound(): void
    {
        $this->writeAdsPageWithoutForm();

        $this->expectException(NotFoundException::class);

        $this->handler()->handle(
            new Route('page.create', ['page' => 'ads']),
            new Request('GET', 'p/ads/create'),
        );
    }

    public function testAnEditFormForARowThatDoesNotExistIsNotFound(): void
    {
        $this->writeAdsPageWithForm();

        $this->expectException(NotFoundException::class);

        $this->handler([])->handle(
            new Route('page.edit', ['page' => 'ads', 'id' => '999999']),
            new Request('GET', 'p/ads/999999/edit'),
        );
    }

    public function testACopyFormForARowThatDoesNotExistIsNotFound(): void
    {
        $this->writeAdsPageWithForm();

        $this->expectException(NotFoundException::class);

        $this->handler([])->handle(
            new Route('page.copy', ['page' => 'ads', 'id' => '999999']),
            new Request('GET', 'p/ads/999999/copy'),
        );
    }
}
