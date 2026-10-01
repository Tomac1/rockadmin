<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Permission;
use RockAdmin\Config\ActionReference;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Loader;
use RockAdmin\Config\PageReference;
use RockAdmin\Config\RegionReference;
use RockAdmin\Config\ValueType;
use RockAdmin\Page\ActionDefinition;
use RockAdmin\Page\ActionSchema;
use RockAdmin\Page\ActionType;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\PageSchema;

/**
 * What an action is, and what a page file may not say about one.
 *
 * Three kinds of refusal live in three places, and the split is the point:
 * `open` is refused by `ActionType::parse()` because it is a syntax fact; a
 * `to` that is not a reference, a confirmed `link` and an unconfirmed `post`
 * are refused by `PageRepository` because that is where every other sibling
 * refusal lives; a `when` naming a column and a `permission` naming a grant
 * are refused where the columns and the catalogue exist, which for the
 * catalogue is not the page layer at all.
 */
#[CoversClass(ActionType::class)]
#[CoversClass(ActionSchema::class)]
#[CoversClass(ActionDefinition::class)]
final class ActionSchemaTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-actions-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    // ------------------------------------------------------- the type

    public function testTheThreeTypesTheSpecificationDocumentsAreDeclared(): void
    {
        $declared = array_map(static fn (ActionType $case): string => $case->value, ActionType::cases());

        $this->assertSame(['link', 'post', 'open'], $declared);
    }

    /** @return array<string, array{string}> */
    public static function buildableTypes(): array
    {
        return ['link' => ['link'], 'post' => ['post'], 'open' => ['open']];
    }

    #[DataProvider('buildableTypes')]
    public function testTheTypesThatWorkParse(string $value): void
    {
        $this->assertSame($value, ActionType::parse($value)->value);
    }

    /**
     * `open` was declared and refused for two milestones, with a message
     * naming the one that would build it — a silent "unknown type" for
     * something the reference manual describes at length is worse than saying
     * "not yet". Milestone 9 is that milestone, so it parses now, and what is
     * pinned here is that all three types are buildable rather than two.
     */
    public function testEveryTypeTheSpecificationNamesIsBuildable(): void
    {
        $this->assertSame(['link', 'post', 'open'], ActionType::buildableNames());
        $this->assertSame(ActionType::Open, ActionType::parse('open'));
    }

    public function testEachTypeNamesTheKeyItsDestinationIsWrittenUnder(): void
    {
        // An `open` carries a `target`, not a `to`, and the specification
        // names it differently on purpose: it does not go anywhere. The page
        // stays where it is and a region appears over it.
        $this->assertSame('to', ActionType::Link->destinationKey());
        $this->assertSame('to', ActionType::Post->destinationKey());
        $this->assertSame('target', ActionType::Open->destinationKey());
    }

    public function testATypeOneEditAwayNamesTheNearest(): void
    {
        try {
            ActionType::parse('lnk');
            $this->fail("The action type 'lnk' was accepted.");
        } catch (PageException $e) {
            $this->assertStringContainsString("Did you mean 'link'", $e->getMessage());
        }
    }

    public function testATypeNearNothingListsThemAll(): void
    {
        try {
            ActionType::parse('kaboom');
            $this->fail("The action type 'kaboom' was accepted.");
        } catch (PageException $e) {
            $this->assertStringNotContainsString('Did you mean', $e->getMessage());
            $this->assertStringContainsString("'link', 'post'", $e->getMessage());
        }
    }

    public function testOnlyAPostWrites(): void
    {
        $this->assertTrue(ActionType::Post->isWrite());
        $this->assertFalse(ActionType::Link->isWrite());
    }

    // ------------------------------------------------------- the schema

    /** @return array<string, array{string}> */
    public static function actionKeys(): array
    {
        $keys = [
                'type', 'label', 'icon', 'to', 'target', 'in', 'size', 'title', 'params',
                'permission', 'confirm', 'when', 'set', 'refresh',
            ];

        return array_combine($keys, array_map(static fn (string $key): array => [$key], $keys));
    }

    #[DataProvider('actionKeys')]
    public function testEveryKeyIsDeclaredWithADescriptionAndAnExample(string $name): void
    {
        $key = ActionSchema::create()->key($name);

        $this->assertNotNull($key, "The action schema does not declare '{$name}'.");
        $this->assertNotSame('', $key->description, "'{$name}' has no description.");
        $this->assertNotNull($key->example, "'{$name}' has no example.");
    }

    public function testTheActionSchemaDeclaresNothingElse(): void
    {
        $this->assertSame(
            [
                'type', 'label', 'icon', 'to', 'target', 'in', 'size', 'title', 'params',
                'permission', 'confirm', 'when', 'set', 'refresh',
            ],
            ActionSchema::create()->names(),
        );
    }

    public function testAPageDeclaresAnActionsBlockOfUniformEntries(): void
    {
        $key = PageSchema::create()->key('actions');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Array, $key->type);
        $this->assertNotNull($key->each, 'Every action must be validated against the same schema.');
        $this->assertNotNull($key->each->key('confirm'));
    }

    // ------------------------------------------------------- what loads

    public function testAnActionIsBuiltFromEverythingItDeclares(): void
    {
        $this->writePageWithActions(<<<'PHP'
            'archive' => [
                'type' => 'post',
                'to' => '@action:archive',
                'set' => ['state' => 'archived'],
                'icon' => 'archive',
                'label' => 'Put away',
                'permission' => 'ads.update',
                'confirm' => 'Archive this ad?',
                'when' => ['state' => 'active'],
                'refresh' => ['grid'],
            ],
            PHP);

        $action = $this->repository()->get('ads')->action('archive');

        $this->assertSame('archive', $action->key);
        $this->assertSame('Put away', $action->label);
        $this->assertSame(ActionType::Post, $action->type);
        $this->assertEquals(new ActionReference('archive'), $action->to);
        $this->assertSame('archive', $action->icon);
        $this->assertSame('ads.update', $action->permission);
        $this->assertSame('Archive this ad?', $action->confirm);
        $this->assertSame(['state' => 'active'], $action->when);
        $this->assertSame(['grid'], $action->refresh);
    }

    public function testAnActionWithoutALabelIsNamedAfterItsKey(): void
    {
        $this->writePageWithActions(<<<'PHP'
            'edit_row' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],
            PHP);

        $this->assertSame('Edit row', $this->repository()->get('ads')->action('edit_row')->label);
    }

    /**
     * The reference is kept as the object the resolver produced, so the
     * renderer that builds a row's URL has the page and the suffix in hand
     * rather than a string to parse a second time.
     */
    public function testALinkKeepsTheReferenceItWasGivenRatherThanItsText(): void
    {
        $this->writePageWithActions(<<<'PHP'
            'edit' => ['type' => 'link', 'to' => '@page:ads/{id}/edit'],
            PHP);

        $this->assertEquals(
            new PageReference('ads', '{id}/edit'),
            $this->repository()->get('ads')->action('edit')->to,
        );
    }

    /**
     * `PageRepository::assertPageExists()` is where a `@page:` reference is
     * judged, because the catalogue of pages is here and not in `Resolver`.
     * An action is one more thing that carries one, so it asks that method
     * rather than growing a second rule.
     */
    public function testAToNamingAPageThatDoesNotExistIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'author' => ['type' => 'link', 'to' => '@page:adz'],
            PHP);

        $this->assertStringContainsString("action 'author'", $message);
        $this->assertStringContainsString('@page:adz', $message);
        $this->assertStringContainsString("Did you mean 'ads'", $message);
    }

    public function testAnUnknownActionNamesTheNearest(): void
    {
        $this->writePageWithActions(<<<'PHP'
            'archive' => ['type' => 'link', 'to' => '@page:ads'],
            PHP);

        try {
            $this->repository()->get('ads')->action('archiv');
            $this->fail("The action 'archiv' was found.");
        } catch (PageException $e) {
            $this->assertStringContainsString("Did you mean 'archive'", $e->getMessage());
        }
    }

    // ------------------------------------------------------- what is refused

    public function testABareUrlIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'offsite' => ['type' => 'link', 'to' => 'https://example.test/admin'],
            PHP);

        $this->assertStringContainsString("Page 'ads'", $message);
        $this->assertStringContainsString("action 'offsite'", $message);
        $this->assertStringContainsString('https://example.test/admin', $message);
        $this->assertStringContainsString('@page:', $message);
    }

    public function testAnActionWithNoDestinationIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'nowhere' => ['type' => 'link'],
            PHP);

        $this->assertStringContainsString("action 'nowhere'", $message);
        $this->assertStringContainsString("'to'", $message);
    }

    public function testALinkPointingAtARegionIsRefused(): void
    {
        // A region is not somewhere to navigate to -- it is a fragment, and
        // landing on one is an unstyled page with no navigation. The refusal
        // comes from the namespace check, which is the same one that stops a
        // `post` pointing at a page.
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'link', 'to' => '@region:detail'],
            PHP);

        $this->assertStringContainsString("action 'peek'", $message);
        $this->assertStringContainsString('@region:', $message);
    }

    public function testAnOpenWritingToRatherThanTargetIsRefused(): void
    {
        // The two keys are one word apart and mean different things. Reading
        // `to` for an `open` would make the destination silently absent.
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'open', 'in' => 'modal', 'to' => '@region:detail'],
            PHP);

        $this->assertStringContainsString("'to'", $message);
        $this->assertStringContainsString("'target'", $message);
    }

    public function testALinkWritingTargetRatherThanToIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'link', 'target' => '@region:detail'],
            PHP);

        $this->assertStringContainsString("'target'", $message);
        $this->assertStringContainsString("'to'", $message);
    }

    public function testAnOpenWithNoPlaceToPutItIsRefused(): void
    {
        // There is no default `in`, and guessing one means finding out which
        // you got by clicking.
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'open', 'target' => '@region:detail'],
            PHP);

        $this->assertStringContainsString("action 'peek'", $message);
        $this->assertStringContainsString("'in'", $message);
    }

    public function testAnOverlayNobodyDefinedNamesTheNearest(): void
    {
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'open', 'in' => 'modl', 'target' => '@region:detail'],
            PHP);

        $this->assertStringContainsString("Did you mean 'modal'", $message);
    }

    public function testASizeTheStylesheetDoesNotDefineIsRefused(): void
    {
        // `ra-btn--xs` with a different prefix: a modifier nothing styles
        // does nothing while reading as though it had been set, and that one
        // shipped and was found by somebody looking at a flat button.
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'open', 'in' => 'offcanvas', 'size' => 'xl', 'target' => '@region:detail'],
            PHP);

        $this->assertStringContainsString("'xl'", $message);
        $this->assertStringContainsString('does not define', $message);
    }

    public function testASizeTheOtherOverlayDefinesIsStillRefused(): void
    {
        // `xl` is a real modal width and not an offcanvas one. The sizes are
        // per overlay, so a list shared between them would accept this.
        $modal = $this->build(<<<'PHP'
            'peek' => ['type' => 'open', 'in' => 'modal', 'size' => 'xl', 'target' => '@region:detail'],
            PHP);

        $this->assertSame('xl', $modal->actions['peek']->size);
    }

    public function testALinkWithAnOverlayKeyIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'link', 'to' => '@page:ads', 'in' => 'modal'],
            PHP);

        $this->assertStringContainsString("'in'", $message);
        $this->assertStringContainsString("'link'", $message);
    }

    public function testAnOpenCarriesWhatItWasGiven(): void
    {
        $page = $this->build(<<<'PHP'
            'peek' => [
                'type' => 'open',
                'in' => 'offcanvas',
                'size' => 'lg',
                'title' => 'Ad #{id}',
                'target' => '@region:detail',
                'params' => ['id' => '{id}'],
                'permission' => 'ads.view',
            ],
            PHP);

        $action = $page->actions['peek'];

        $this->assertSame(ActionType::Open, $action->type);
        $this->assertSame('offcanvas', $action->in);
        $this->assertSame('lg', $action->size);
        $this->assertSame('Ad #{id}', $action->title);
        $this->assertSame(['id' => '{id}'], $action->params);
        $this->assertInstanceOf(RegionReference::class, $action->to);
        $this->assertSame('detail', $action->to->region);
        $this->assertNull($action->to->page, 'A region of this page carries no page name.');
    }

    public function testAnOpenWithAConfirmationIsRefused(): void
    {
        // It opens something and changes nothing. A confirmation in front of
        // that teaches people to dismiss confirmations, including the ones in
        // front of a `post`.
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'open', 'in' => 'modal', 'target' => '@region:detail', 'confirm' => 'Sure?'],
            PHP);

        $this->assertStringContainsString("'confirm'", $message);
    }

    public function testAnOpenWithASetIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'open', 'in' => 'modal', 'target' => '@region:detail', 'set' => ['a' => 'b']],
            PHP);

        $this->assertStringContainsString("'set'", $message);
    }

    public function testAParamsOnSomethingThatFetchesNothingIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'peek' => ['type' => 'link', 'to' => '@page:ads', 'params' => ['id' => '{id}']],
            PHP);

        $this->assertStringContainsString("'params'", $message);
    }

    public function testAPostWithNoConfirmationIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'archive' => ['type' => 'post', 'to' => '@action:archive', 'set' => ['state' => 'archived']],
            PHP);

        $this->assertStringContainsString("action 'archive'", $message);
        $this->assertStringContainsString('confirm', $message);
    }

    public function testALinkWithAConfirmationIsRefused(): void
    {
        $message = $this->refusal(<<<'PHP'
            'edit' => ['type' => 'link', 'to' => '@page:ads', 'confirm' => 'Sure?'],
            PHP);

        $this->assertStringContainsString("action 'edit'", $message);
        $this->assertStringContainsString('confirm', $message);
    }

    public function testALinkMustPointAtAPageAndAPostAtAnActionHandler(): void
    {
        $link = $this->refusal(<<<'PHP'
            'edit' => ['type' => 'link', 'to' => '@action:archive'],
            PHP);

        $this->assertStringContainsString('@page:', $link);

        $post = $this->refusal(<<<'PHP'
            'archive' => ['type' => 'post', 'to' => '@page:ads', 'confirm' => 'Sure?'],
            PHP);

        $this->assertStringContainsString('@action:', $post);
    }

    public function testAWhenNamingAColumnTheRegionDoesNotDeclareNamesTheNearest(): void
    {
        $message = $this->refusal(<<<'PHP'
            'archive' => [
                'type' => 'post',
                'to' => '@action:archive',
                'set' => ['state' => 'archived'],
                'confirm' => 'Archive?',
                'when' => ['stat' => 'active'],
            ],
            PHP);

        $this->assertStringContainsString("action 'archive'", $message);
        $this->assertStringContainsString("'stat'", $message);
        $this->assertStringContainsString("Did you mean 'state'", $message);
    }

    public function testARefreshNamingARegionThePageDoesNotHaveNamesTheNearest(): void
    {
        $message = $this->refusal(<<<'PHP'
            'archive' => [
                'type' => 'post',
                'to' => '@action:archive',
                'set' => ['state' => 'archived'],
                'confirm' => 'Archive?',
                'refresh' => ['grd'],
            ],
            PHP);

        $this->assertStringContainsString("Did you mean 'grid'", $message);
    }

    public function testARowConditionComparesAColumnToOneValue(): void
    {
        $message = $this->refusal(<<<'PHP'
            'archive' => [
                'type' => 'post',
                'to' => '@action:archive',
                'set' => ['state' => 'archived'],
                'confirm' => 'Archive?',
                'when' => ['state' => ['active', 'paused']],
            ],
            PHP);

        $this->assertStringContainsString("action 'archive'", $message);
        $this->assertStringContainsString("'state'", $message);
    }

    // ------------------------------------------------------- the catalogue

    /**
     * The catalogue is generated from every page, and `Permission` lives in
     * `Auth` precisely because it needs `Page`. A page repository that
     * checked the catalogue itself would have to depend back on `Auth`, so
     * this refusal happens where `Roles` is already refused: when the gate is
     * built.
     */
    public function testAPermissionOutsideTheCatalogueIsRefusedWhenTheGateIsBuilt(): void
    {
        $this->writePageWithActions(<<<'PHP'
            'archive' => [
                'type' => 'post',
                'to' => '@action:archive',
                'set' => ['state' => 'archived'],
                'confirm' => 'Archive?',
                'permission' => 'ads.updat',
            ],
            PHP);
        file_put_contents($this->root . '/roles.php', "<?php\n\nreturn ['editor' => ['permissions' => ['ads.*']]];\n");

        try {
            Gate::fromConfig(
                (new Loader($this->root, static fn (string $name): ?string => null))->load(),
                $this->repository(),
            );
            $this->fail('A permission outside the catalogue was accepted.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("Page 'ads'", $e->getMessage());
            $this->assertStringContainsString("action 'archive'", $e->getMessage());
            $this->assertStringContainsString('ads.updat', $e->getMessage());
            $this->assertStringContainsString("Did you mean 'ads.update'", $e->getMessage());
        }
    }

    /**
     * The gate's own closed set of verbs is `create`, `update` and `delete`,
     * so a page generates no `publish` permission however much configuration
     * asks for one. That is refused here rather than silently denied at every
     * request, which is what Task 5 has to keep true when a configured verb
     * reaches `POST /a/{page}/{action}`.
     */
    public function testAPermissionNamingAVerbNoPageGeneratesIsRefused(): void
    {
        $this->writePageWithActions(<<<'PHP'
            'publish' => [
                'type' => 'post',
                'to' => '@action:publish',
                'set' => ['state' => 'archived'],
                'confirm' => 'Publish?',
                'permission' => 'ads.publish',
            ],
            PHP);

        $pages = $this->repository();

        try {
            Permission::assertActionsAreGrantable($pages, Permission::catalogue($pages));
            $this->fail('A permission outside the catalogue was accepted.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("Page 'ads'", $e->getMessage());
            $this->assertStringContainsString("action 'publish'", $e->getMessage());
            $this->assertStringContainsString('ads.publish', $e->getMessage());
        }
    }

    public function testAPermissionInTheCatalogueIsAccepted(): void
    {
        $this->writePageWithActions(<<<'PHP'
            'archive' => [
                'type' => 'post',
                'to' => '@action:archive',
                'set' => ['state' => 'archived'],
                'confirm' => 'Archive?',
                'permission' => 'ads.update',
            ],
            PHP);

        $pages = $this->repository();
        Permission::assertActionsAreGrantable($pages, Permission::catalogue($pages));

        $this->expectNotToPerformAssertions();
    }

    // ------------------------------------------------------- fixtures

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    /** The message of the refusal the given actions block produces. */
    private function refusal(string $actions): string
    {
        $this->writePageWithActions($actions);

        try {
            $this->repository()->get('ads');
        } catch (PageException $e) {
            return $e->getMessage();
        }

        $this->fail('The page loaded.');
    }

    /** The page, built, for the cases that are about what was accepted. */
    private function build(string $actions): PageDefinition
    {
        $this->writePageWithActions($actions);

        return $this->repository()->get('ads');
    }

    private function writePageWithActions(string $actions): void
    {
        $body = "<?php\n\nreturn [\n"
            . "    'title' => 'Ads',\n"
            . "    'entity' => ['table' => 'ads'],\n"
            . "    'actions' => [\n{$actions}\n    ],\n"
            . "    'regions' => [\n"
            . "        'grid' => ['type' => 'list', 'columns' => [\n"
            . "            'id' => [], 'title' => [], 'state' => [],\n"
            . "        ]],\n"
            . "        'form' => ['type' => 'form', 'form' => ['fields' => ['title' => []]]],\n"
            . "    ],\n"
            . "];\n";

        file_put_contents($this->root . '/pages/ads.php', $body);
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
