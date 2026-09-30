<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Enums;
use RockAdmin\Db\DbException;
use RockAdmin\Db\NoSuchRowException;
use RockAdmin\Db\Result;
use RockAdmin\Db\Sql;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Http\ActionHandler;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\FakeRowSource;
use RockAdmin\Tests\Support\RecordingWriteHandler;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;
use Throwable;

/**
 * `ActionHandler` serves `POST /a/{page}/{action}` for the three verbs this
 * milestone ships: create, update and delete.
 *
 * The shape being pinned here is the order of the checks and what each
 * failure answers with, because every one of them is a decision somebody
 * could plausibly get wrong in the opposite direction: a missing CSRF token
 * is a 403 rather than a redirect, because a redirect hides the failure; a
 * refused submission is a 422 carrying what was typed rather than a redirect,
 * because a redirect loses it; and a database integrity error is a form error
 * rather than a 500, because the database is the second validator and the
 * honest one.
 */
#[CoversClass(ActionHandler::class)]
final class ActionHandlerTest extends TestCase
{
    private string $root;

    private SessionStore $session;

    private RecordingWriteHandler $writes;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-action-handler-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);

        $this->session = new ArraySessionStore();
        $this->writes = new RecordingWriteHandler();
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

    /**
     * `title` is required, so an empty one is the refusal every test that
     * needs a rejection uses. `state` is hidden with a default, so it is the
     * field that proves a default is applied on save for something the form
     * never offered (spec 7.6).
     */
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
                                'title' => ['type' => 'text', 'required' => true],
                                'price' => ['type' => 'number'],
                                'state' => ['type' => 'text', 'hidden' => true, 'default' => 'draft'],
                            ],
                        ],
                    ],
                ],
            ]
            PHP);
    }

    /**
     * Two form regions, in declaration order. A rejection must be redrawn
     * from the one the submission came from -- `firstFormRegion()` -- because
     * a rejection drawn from a different region would silently swap the form
     * under the person who was filling it in.
     */
    private function writeAdsPageWithTwoForms(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'form' => [
                        'type' => 'form',
                        'form' => ['fields' => ['title' => ['type' => 'text', 'required' => true]]],
                    ],
                    'other' => [
                        'type' => 'form',
                        'form' => ['fields' => ['headline' => ['type' => 'text']]],
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
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
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

    private function csrf(): Csrf
    {
        return new Csrf($this->session);
    }

    private function handler(?Throwable $writeFails = null): ActionHandler
    {
        $urls = new UrlGenerator('/admin');
        $csrf = $this->csrf();
        $rows = new FakeRowSource([
            new Result([['id' => 1, 'title' => 'Horské kolo', 'price' => 900]], null, [new Sql('SELECT 1')]),
        ]);
        $region = new FormRegion($rows, $urls, $csrf);
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);
        $flashes = new FlashBag($this->session);

        $this->writes = new RecordingWriteHandler($writeFails);

        return new ActionHandler(
            $this->repository(),
            $region,
            new FieldValidator(),
            $this->writes,
            new FormHandler(
                $this->repository(),
                $region,
                $renderer,
                new Assets($urls),
                $flashes,
                $urls,
                'RockAdmin Demo',
                'auto',
            ),
            $csrf,
            $flashes,
            $urls,
        );
    }

    /**
     * @param  array<string, mixed> $body the CSRF token is added, since every
     *                                    test but the CSRF ones needs a valid one
     * @return array<string, mixed>
     */
    private function signed(array $body): array
    {
        return [Csrf::FIELD => $this->csrf()->token()] + $body;
    }

    /** @return list<string> the flash messages waiting in the session */
    private function flashes(): array
    {
        $messages = [];

        foreach ((new FlashBag($this->session))->take() as $flash) {
            $messages[] = $flash->message;
        }

        return $messages;
    }

    public function testAMissingCsrfTokenIsForbiddenRatherThanARedirect(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        try {
            $handler->handle(
                new Route('action', ['page' => 'ads', 'action' => 'create']),
                new Request('POST', 'a/ads/create', body: ['title' => 'New']),
            );
            $this->fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $e) {
            $this->assertSame(403, $e->status);
            $this->assertStringContainsString('CSRF', $e->getMessage());
        }

        $this->assertSame([], $this->writes->calls);
    }

    public function testAWrongCsrfTokenIsForbidden(): void
    {
        $this->writeAdsPageWithForm();

        $this->expectException(ForbiddenException::class);

        $this->handler()->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: [Csrf::FIELD => 'nope', 'title' => 'New']),
        );
    }

    public function testTheTokenMayArriveInTheHeaderInstead(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request(
                'POST',
                'a/ads/create',
                body: ['title' => 'New'],
                headers: [Csrf::HEADER => $this->csrf()->token()],
            ),
        );

        $this->assertSame(302, $response->status);
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(
            new Route('action', ['page' => 'nope', 'action' => 'create']),
            new Request('POST', 'a/nope/create', body: $this->signed(['title' => 'New'])),
        );
    }

    public function testAnUnknownActionIsNotFound(): void
    {
        $this->writeAdsPageWithForm();

        $this->expectException(NotFoundException::class);

        $this->handler()->handle(
            new Route('action', ['page' => 'ads', 'action' => 'publish']),
            new Request('POST', 'a/ads/publish', body: $this->signed([])),
        );
    }

    public function testAPageWithNoFormRegionIsNotFound(): void
    {
        $this->writeAdsPageWithoutForm();

        $this->expectException(NotFoundException::class);

        $this->handler()->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed(['title' => 'New'])),
        );
    }

    public function testACreateWritesOnceAndRedirectsToTheReturnAddress(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed([
                'title' => 'New',
                'price' => '12',
                FormFields::RETURN_TO => '/admin/p/ads?grid%5Bpage%5D=3',
            ])),
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/p/ads?grid%5Bpage%5D=3', $response->headers['location']);
        $this->assertCount(1, $this->writes->calls);
        $this->assertSame('insert', $this->writes->calls[0][0]);
        $this->assertSame('New', $this->writes->calls[0][2]['title']);
        $this->assertSame(12, $this->writes->calls[0][2]['price']);
        $this->assertNotSame([], $this->flashes());
    }

    public function testACreateAppliesTheDefaultOfAFieldTheFormNeverOffered(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed(['title' => 'New', 'state' => 'live'])),
        );

        // 'draft', not 'live': a hidden field's value comes from its default
        // on save, never from the wire.
        $this->assertSame('draft', $this->writes->calls[0][2]['state']);
    }

    public function testAHostileReturnAddressRedirectsToThePageRatherThanToWhatWasSent(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed([
                'title' => 'New',
                FormFields::RETURN_TO => 'https://evil.com/',
            ])),
        );

        $this->assertSame('/admin/p/ads', $response->headers['location']);
    }

    public function testAnUpdateWritesTheKeyTheBodyCarries(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'update']),
            new Request('POST', 'a/ads/update', body: $this->signed([
                FormFields::ID => '1',
                'title' => 'Renamed',
            ])),
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('update', $this->writes->calls[0][0]);
        $this->assertSame('1', $this->writes->calls[0][1]);
        $this->assertSame('Renamed', $this->writes->calls[0][2]['title']);
    }

    public function testAnUpdateWithNoKeyIsNotFound(): void
    {
        $this->writeAdsPageWithForm();

        $this->expectException(NotFoundException::class);

        $this->handler()->handle(
            new Route('action', ['page' => 'ads', 'action' => 'update']),
            new Request('POST', 'a/ads/update', body: $this->signed(['title' => 'Renamed'])),
        );
    }

    public function testADeleteRemovesTheRowWithoutTrustingTheBrowsersConfirmation(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        // No data-ra-confirm, no confirmation flag, nothing from the browser
        // saying anybody agreed: the server does not look for one, it checks
        // the token and the method, which is all it can verify.
        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'delete']),
            new Request('POST', 'a/ads/delete', body: $this->signed([FormFields::ID => '1'])),
        );

        $this->assertSame(302, $response->status);
        $this->assertSame([['delete', '1', []]], $this->writes->calls);
        $this->assertNotSame([], $this->flashes());
    }

    public function testADeleteWithNoKeyIsNotFound(): void
    {
        $this->writeAdsPageWithForm();

        $this->expectException(NotFoundException::class);

        $this->handler()->handle(
            new Route('action', ['page' => 'ads', 'action' => 'delete']),
            new Request('POST', 'a/ads/delete', body: $this->signed([])),
        );
    }

    public function testARefusedSubmissionComesBackAt422WithWhatWasTypedAndNothingWritten(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed([
                'title' => '',
                'price' => 'not a number',
            ])),
        );

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('<!doctype html>', $response->body);
        $this->assertStringContainsString('value="not a number"', $response->body);
        $this->assertStringContainsString('Title', $response->body);
        $this->assertSame([], $this->writes->calls);
    }

    public function testARefusedUpdateKeepsTheKeySoSavingAgainStillNamesTheRow(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler();

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'update']),
            new Request('POST', 'a/ads/update', body: $this->signed([
                FormFields::ID => '1',
                'title' => '',
            ])),
        );

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('name="' . FormFields::ID . '" value="1"', $response->body);
        $this->assertStringContainsString('action="/admin/a/ads/update"', $response->body);
    }

    public function testARejectionIsRedrawnFromTheRegionTheSubmissionCameFrom(): void
    {
        $this->writeAdsPageWithTwoForms();
        $handler = $this->handler();

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed(['title' => ''])),
        );

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('name="title"', $response->body);
        $this->assertStringNotContainsString('name="headline"', $response->body);
    }

    public function testAnIntegrityErrorBecomesAFormLevelErrorAt422(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler(new DbException(
            'Duplicate entry',
            sqlState: '23505',
        ));

        $response = $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed(['title' => 'Horské kolo'])),
        );

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('ra-form-errors', $response->body);
        $this->assertStringContainsString('23505', $response->body);
        // What was typed survives, so the person can change the one thing
        // the database refused.
        $this->assertStringContainsString('value="Horské kolo"', $response->body);
    }

    public function testWritingARowThatHasSinceGoneIsNotFoundRatherThanAServerError(): void
    {
        // A plain DbException carries no SQLSTATE, so it is indistinguishable
        // from an internal fault and becomes a 500 -- which is what this was
        // before NoSuchRowException existed. Two people editing one row is an
        // ordinary race, not a broken server, and the person who loses it
        // should be told the row is gone.
        $this->writeAdsPageWithForm();
        $handler = $this->handler(NoSuchRowException::for('ads', '1', 'update'));

        try {
            $handler->handle(
                new Route('action', ['page' => 'ads', 'action' => 'update']),
                new Request('POST', 'a/ads/update', body: $this->signed([
                    FormFields::ID => '1',
                    'title' => 'Edited',
                ])),
            );

            $this->fail('Updating a row that is gone should be refused.');
        } catch (NotFoundException $e) {
            $this->assertSame(404, $e->status);
            $this->assertInstanceOf(NoSuchRowException::class, $e->getPrevious());
        }
    }

    public function testADeleteOfARowThatHasSinceGoneIsAlsoNotFound(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler(NoSuchRowException::for('ads', '1', 'delete'));

        $this->expectException(NotFoundException::class);

        $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'delete']),
            new Request('POST', 'a/ads/delete', body: $this->signed([FormFields::ID => '1'])),
        );
    }

    public function testAnyOtherDatabaseErrorPropagatesRatherThanBecomingAFormError(): void
    {
        $this->writeAdsPageWithForm();
        $handler = $this->handler(new DbException('Connection lost', sqlState: '08006'));

        $this->expectException(DbException::class);

        $handler->handle(
            new Route('action', ['page' => 'ads', 'action' => 'create']),
            new Request('POST', 'a/ads/create', body: $this->signed(['title' => 'New'])),
        );
    }
}
