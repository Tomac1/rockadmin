<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Gate;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\Sql;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Http\ActionHandler;
use RockAdmin\Http\ActionRequest;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;
use RockAdmin\Tests\Support\FakeRowSource;
use RockAdmin\Tests\Support\RecordingWriteHandler;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * A page's own `post` action, performed.
 *
 * Three verbs used to be the whole of `POST /a/{page}/{action}`, and the gate
 * refused anything else on purpose: without that, `POST /a/ads/publish` would
 * ask for a permission named `ads.publish`, and the day somebody added a
 * `publish` action it would silently start being granted by `ads.*`.
 *
 * A page may now declare verbs of its own and that property has to survive.
 * It does, because the set is still closed — over configuration instead of
 * over three literals. An action says what permission it needs; a verb no
 * action declares is still refused; and nothing is derived from the URL.
 *
 * The other half of the milestone's own brief is here too: `when` decides
 * whether an action is *offered*, which is a statement about a control drawn
 * minutes ago on a row that may have changed since — or never drawn at all,
 * by a client that posted the URL anyway. A condition enforced only where the
 * button is drawn is not enforced.
 */
#[CoversClass(ActionHandler::class)]
#[CoversClass(ActionRequest::class)]
#[CoversClass(Gate::class)]
final class CustomActionTest extends TestCase
{
    private string $root;

    private SessionStore $session;

    private RecordingWriteHandler $writes;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-custom-action-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);

        $this->session = new ArraySessionStore();
        $this->writes = new RecordingWriteHandler();
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    // --- the write itself ---

    public function testAConfiguredPostWritesWhatItsSetNames(): void
    {
        $this->writeAdsPage();

        $response = $this->handler()->handle($this->route('archive'), $this->post(['id' => '4']));

        $this->assertSame(302, $response->status);
        $this->assertSame([['update', '4', ['state' => 'archived']]], $this->writes->calls);
    }

    public function testTheFlashNamesTheActionRatherThanCallingItASave(): void
    {
        // "Saved Ads 4." after pressing Archive reads as the wrong button
        // having been pressed. The action's own label is the only text that
        // describes what happened.
        $this->writeAdsPage();

        $this->handler()->handle($this->route('archive'), $this->post(['id' => '4']));

        $this->assertSame(['Archive: Ads 4.'], $this->flashes());
    }

    public function testAnActionPointedAtDeleteDeletesTheRow(): void
    {
        // Acceptance 2 of the milestone: a row is deleted from a grid, from
        // configuration alone. `@action:delete` reaches the verb the admin
        // already has, so no `set` has to spell out what deletion means.
        //
        // The URL is the verb the reference names, not the action's own key:
        // `bin` posts to `/a/ads/delete`. That is the whole point of pointing
        // an action at an existing verb, and it is why the action key and the
        // verb are separate things.
        $this->writeAdsPage();

        $bin = $this->repository()->get('ads')->actions['bin'];

        $this->assertTrue($bin->isDelete());
        $this->assertSame('delete', $bin->verb());

        $this->handler()->handle($this->route('delete'), $this->post(['id' => '4']));

        $this->assertSame([['delete', '4', []]], $this->writes->calls);
    }

    public function testAnActionNamingNoRowIsRefusedBeforeAnythingIsWritten(): void
    {
        $this->writeAdsPage();

        $this->expectException(NotFoundException::class);

        try {
            $this->handler()->handle($this->route('archive'), $this->post([]));
        } finally {
            $this->assertSame([], $this->writes->calls);
        }
    }

    // --- the verb ---

    public function testAVerbNoActionDeclaresIsNotFound(): void
    {
        $this->writeAdsPage();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("has no action 'publish'");

        $this->handler()->handle($this->route('publish'), $this->post(['id' => '4']));
    }

    public function testTheRefusalListsTheVerbsThePageDoesAccept(): void
    {
        // A page that declares `archive` and is posted `archvie` should say
        // so, not imply that `archive` was never an option.
        $this->writeAdsPage();

        try {
            $this->handler()->handle($this->route('archvie'), $this->post(['id' => '4']));
            $this->fail('An unknown verb was accepted.');
        } catch (NotFoundException $e) {
            $this->assertStringContainsString('create', $e->getMessage());
            $this->assertStringContainsString('delete', $e->getMessage());
            $this->assertStringContainsString('archive', $e->getMessage());
        }
    }

    public function testALinkActionDoesNotBecomeAPostableVerb(): void
    {
        // `edit` is a `link`. It navigates, it has no handler, and posting to
        // it must not reach one — a GET-only control that answers POST is the
        // mismatch `ActionType::referenceNamespace()` exists to prevent at
        // load, and this is the same refusal at request time.
        $this->writeAdsPage();

        $this->expectException(NotFoundException::class);

        $this->handler()->handle($this->route('edit'), $this->post(['id' => '4']));
    }

    // --- the gate ---

    public function testTheGateAsksTheActionWhatItNeedsRatherThanTheUrl(): void
    {
        $this->writeAdsPage();

        $gate = Access::gateFor($this->repository());

        $this->assertSame(
            'ads.update',
            $gate->permissionForRequest($this->route('archive')),
            'The verb `archive` must cost what its action declares, never a permission derived from '
            . 'the URL: `ads.archive` would be a verb granting itself by being spelled.',
        );
    }

    public function testAVerbNoActionDeclaresIsStillRefusedByTheGate(): void
    {
        $this->writeAdsPage();

        $gate = Access::gateFor($this->repository());

        $this->expectException(ForbiddenException::class);

        $gate->permissionForRequest($this->route('publish'));
    }

    public function testAReaderWithoutTheActionsPermissionIsRefused(): void
    {
        $this->writeAdsPage();

        $gate = Access::gateFor($this->repository(), [
            Access::ROLE => ['permissions' => ['ads.view']],
        ]);

        $this->assertFalse($gate->allows(Access::identity()->user(), 'ads.update'));
    }

    public function testTheBuiltInVerbsStillCostWhatTheyAlwaysDid(): void
    {
        // The configured half is a fallback, reached only when the static
        // mapping has nothing. A page declaring an action must not be able to
        // change what `delete` costs.
        $this->writeAdsPage();

        $gate = Access::gateFor($this->repository());

        $this->assertSame('ads.delete', $gate->permissionForRequest($this->route('delete')));
        $this->assertSame('ads.create', $gate->permissionForRequest($this->route('create')));
        $this->assertSame('ads.update', $gate->permissionForRequest($this->route('update')));
    }

    public function testAnActionDefinedOnlyInsideAColumnIsNotAPostableVerb(): void
    {
        // An inline action exists in one place and is drawn there. Giving it a
        // URL anybody may post to would make "defined inline" a way of adding
        // a route without saying so. The gate and the handler read the same
        // block by the same rule, or a request is authorised as one thing and
        // performed as another.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'grid' => [
                        'type' => 'list',
                        'columns' => [
                            'title' => [],
                            'state' => [],
                            'tools' => [
                                'type' => 'actions',
                                'items' => [
                                    'hide' => [
                                        'type' => 'post',
                                        'to' => '@action:hide',
                                        'confirm' => 'Hide it?',
                                        'permission' => 'ads.update',
                                        'set' => ['state' => 'hidden'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
            PHP);

        $gate = Access::gateFor($this->repository());

        try {
            $gate->permissionForRequest($this->route('hide'));
            $this->fail('An inline action was reachable as a verb.');
        } catch (ForbiddenException) {
            // expected
        }

        $this->expectException(NotFoundException::class);

        $this->handler()->handle($this->route('hide'), $this->post(['id' => '4']));
    }

    // --- `when`, re-checked ---

    public function testAnActionIsPerformedOnARowItsWhenMatches(): void
    {
        $this->writeAdsPage();

        $this->handler(row: ['id' => 4, 'state' => 'active'])
            ->handle($this->route('archive'), $this->post(['id' => '4']));

        $this->assertSame([['update', '4', ['state' => 'archived']]], $this->writes->calls);
    }

    public function testAnActionIsRefusedOnARowItsWhenDoesNotMatch(): void
    {
        // The client never drew this control, or drew it before the row
        // changed. Either way the server decides.
        $this->writeAdsPage();

        $response = $this->handler(row: ['id' => 4, 'state' => 'sold'])
            ->handle($this->route('archive'), $this->post(['id' => '4']));

        $this->assertSame(302, $response->status);
        $this->assertSame([], $this->writes->calls, 'A row that does not qualify was written to anyway.');
    }

    public function testTheRefusalSaysWhichColumnDisagreed(): void
    {
        $this->writeAdsPage();

        $this->handler(row: ['id' => 4, 'state' => 'sold'])
            ->handle($this->route('archive'), $this->post(['id' => '4']));

        $message = implode(' ', $this->flashes());

        $this->assertStringContainsString('state', $message);
        $this->assertStringContainsString('active', $message);
        $this->assertStringContainsString('sold', $message);
    }

    public function testAConditionIsComparedAsTextTheWayTheRendererCompresIt(): void
    {
        // A page file writes `'state' => 'active'` and a driver may hand back
        // an int where another hands back a string. An action offered by the
        // renderer and refused by the handler, on a row nobody touched, would
        // be unexplainable from either side.
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'actions' => [
                    'feature' => [
                        'type' => 'post',
                        'to' => '@action:feature',
                        'confirm' => 'Feature it?',
                        'permission' => 'ads.update',
                        'when' => ['live' => 1],
                        'set' => ['featured' => true],
                    ],
                ],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => [], 'live' => []]],
                ],
            ]
            PHP);

        $this->handler(row: ['id' => 4, 'live' => '1'])
            ->handle($this->route('feature'), $this->post(['id' => '4']));

        $this->assertSame([['update', '4', ['featured' => true]]], $this->writes->calls);
    }

    public function testAnActionWithNoConditionsReadsNoRowAtAll(): void
    {
        // The read exists for the re-check and for nothing else. An action
        // with no `when` has nothing to check, and paying for a query to
        // discover that would be a query per button press.
        $this->writeAdsPage();

        // A FakeRowSource with nothing in it: asking it for a row is an error,
        // so reaching the write at all proves no read happened.
        $this->handler(rows: new FakeRowSource([]))
            ->handle($this->route('touch'), $this->post(['id' => '4']));

        $this->assertSame([['update', '4', ['state' => 'touched']]], $this->writes->calls);
    }

    public function testAWhenCheckOnAMissingRowIsNotFound(): void
    {
        $this->writeAdsPage();

        $this->expectException(NotFoundException::class);

        $this->handler(rows: new FakeRowSource([new Result([], null, [new Sql('SELECT 1')])]))
            ->handle($this->route('archive'), $this->post(['id' => '404']));
    }

    // --- what configuration refuses ---

    public function testAPostThatWritesNothingIsRefusedAtLoad(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'actions' => [
                    'archive' => ['type' => 'post', 'to' => '@action:archive', 'confirm' => 'Sure?'],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("'post' with no 'set'");

        $this->repository()->get('ads');
    }

    public function testALinkWithASetIsRefusedAtLoad(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'actions' => [
                    'edit' => ['type' => 'link', 'to' => '@page:ads', 'set' => ['state' => 'x']],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("'link' with a 'set'");

        $this->repository()->get('ads');
    }

    public function testADeleteCarryingASetIsRefusedAtLoad(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'actions' => [
                    'bin' => [
                        'type' => 'post',
                        'to' => '@action:delete',
                        'confirm' => 'Sure?',
                        'set' => ['state' => 'gone'],
                    ],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->expectException(PageException::class);
        $this->expectExceptionMessage('only one of them can happen');

        $this->repository()->get('ads');
    }

    public function testASetValueThatIsNotALiteralIsRefusedAtLoad(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'actions' => [
                    'archive' => [
                        'type' => 'post',
                        'to' => '@action:archive',
                        'confirm' => 'Sure?',
                        'set' => ['state' => ['archived']],
                    ],
                ],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->expectException(PageException::class);
        $this->expectExceptionMessage('stores literals');

        $this->repository()->get('ads');
    }

    // --- harness ---

    private function writeAdsPage(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'actions' => [
                    'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit', 'permission' => 'ads.update'],
                    'archive' => [
                        'type' => 'post',
                        'to' => '@action:archive',
                        'label' => 'Archive',
                        'confirm' => 'Archive this ad?',
                        'permission' => 'ads.update',
                        'when' => ['state' => 'active'],
                        'set' => ['state' => 'archived'],
                    ],
                    'bin' => [
                        'type' => 'post',
                        'to' => '@action:delete',
                        'confirm' => 'Delete this ad?',
                        'permission' => 'ads.delete',
                    ],
                    'touch' => [
                        'type' => 'post',
                        'to' => '@action:touch',
                        'confirm' => 'Touch it?',
                        'permission' => 'ads.update',
                        'set' => ['state' => 'touched'],
                    ],
                ],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => [], 'state' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => ['fields' => ['title' => ['type' => 'text', 'required' => true]]],
                    ],
                ],
            ]
            PHP);
    }

    private function writePage(string $name, string $body): void
    {
        file_put_contents($this->root . '/pages/' . $name . '.php', "<?php\n\nreturn {$body};\n");
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    private function route(string $action): Route
    {
        return new Route('action', ['page' => 'ads', 'action' => $action]);
    }

    /**
     * @param  array<string, mixed> $body the token is added, since the token's
     *                                    own refusals are `ActionHandlerTest`'s
     * @return Request
     */
    private function post(array $body): Request
    {
        return new Request(
            'POST',
            '/admin/a/ads/archive',
            body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $this->withId($body),
        );
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function withId(array $body): array
    {
        if (!\array_key_exists('id', $body)) {
            return $body;
        }

        $id = $body['id'];
        unset($body['id']);

        return [FormFields::ID => $id] + $body;
    }

    /** @param array<string, mixed>|null $row the row a `when` check will read */
    private function handler(?array $row = null, ?FakeRowSource $rows = null): ActionHandler
    {
        $urls = new UrlGenerator('/admin');
        $csrf = new Csrf($this->session);

        $rows ??= new FakeRowSource([
            new Result([$row ?? ['id' => 4, 'state' => 'active']], null, [new Sql('SELECT 1')]),
        ]);

        $reader = new RowReader($rows);
        $region = new FormRegion($reader, $urls, $csrf, Access::gateFor($this->repository()), Access::identity());
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);
        $flashes = new FlashBag($this->session);

        $this->writes = new RecordingWriteHandler();

        return new ActionHandler(
            $this->repository(),
            $region,
            new FieldValidator(),
            $this->writes,
            $reader,
            null,
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

    /** @return list<string> the flash messages waiting in the session */
    private function flashes(): array
    {
        $messages = [];

        foreach ((new FlashBag($this->session))->take() as $flash) {
            $messages[] = $flash->message;
        }

        return $messages;
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
}
