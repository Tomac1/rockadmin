<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\SessionStore;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;

/**
 * Which workspace a request is in.
 *
 * Everything here is about not trusting the session, because a session is
 * editable by whoever holds it and a workspace name in one is a claim about
 * what somebody may see. The name is resolved against the configured
 * workspaces *and* against what the user holds, on every request.
 */
#[CoversClass(SessionWorkspace::class)]
final class ActiveWorkspaceTest extends TestCase
{
    public function testSomebodyHoldingOneWorkspaceIsInIt(): void
    {
        // No choice to store and none to get wrong. Most people in a
        // multi-tenant admin work in one tenant, so the common case costs no
        // session writes at all.
        $session = new ArraySessionStore();
        $active = $this->workspace($session);

        $this->assertSame('cyklobazar', $active->current(Access::user(workspaces: ['cyklobazar']))?->name);
        $this->assertNull($session->get('ra.workspace'), 'A user with one workspace wrote to the session.');
    }

    public function testSomebodyHoldingSeveralStartsInTheFirstTheyHold(): void
    {
        // Deterministic, in the order the file declares them, rather than
        // whichever the database happened to return first.
        $active = $this->workspace();

        $this->assertSame('motobazar', $active->current(Access::user(workspaces: ['motobazar', 'lodbazar']))?->name);
    }

    public function testSwitchingIsRemembered(): void
    {
        $session = new ArraySessionStore();
        $active = $this->workspace($session);
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $active->switchTo($this->workspaces()->get('motobazar'));

        $this->assertSame('motobazar', $active->current($user)?->name);
        $this->assertSame('motobazar', $this->workspace($session)->current($user)?->name);
    }

    public function testWhatWasJustChosenIsVisibleWithinTheSameRequest(): void
    {
        // A switch happens during a request that goes on to redirect, and
        // anything asking afterwards should see what was chosen rather than
        // re-reading a session the response has not written yet.
        $active = $this->workspace();
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $active->current($user);
        $active->switchTo($this->workspaces()->get('motobazar'));

        $this->assertSame('motobazar', $active->current($user)?->name);
    }

    // --- the session is a claim, not an answer ---

    public function testASessionNamingAWorkspaceTheUserNoLongerHoldsIsIgnored(): void
    {
        // Access is revoked by changing the user. A session written before
        // that must not outlive it -- otherwise taking somebody out of a
        // tenant leaves them in it until they happen to log out.
        $session = new ArraySessionStore();
        $session->set('ra.workspace', 'motobazar');

        $this->assertSame(
            'cyklobazar',
            $this->workspace($session)->current(Access::user(workspaces: ['cyklobazar', 'lodbazar']))?->name,
        );
    }

    public function testASessionNamingAWorkspaceNobodyConfiguresIsIgnored(): void
    {
        $session = new ArraySessionStore();
        $session->set('ra.workspace', 'deleted_last_week');

        $this->assertSame(
            'cyklobazar',
            $this->workspace($session)->current(Access::user(workspaces: ['cyklobazar', 'motobazar']))?->name,
        );
    }

    public function testASessionCarryingSomethingThatIsNotAStringIsIgnored(): void
    {
        $session = new ArraySessionStore();
        $session->set('ra.workspace', ['motobazar']);

        $this->assertSame(
            'cyklobazar',
            $this->workspace($session)->current(Access::user(workspaces: ['cyklobazar', 'motobazar']))?->name,
        );
    }

    // --- all workspaces ---

    public function testAllWorkspacesIsNullAndNeedsEveryWorkspace(): void
    {
        $session = new ArraySessionStore();
        $active = $this->workspace($session);
        $everything = Access::user(workspaces: ['cyklobazar', 'motobazar', 'lodbazar']);

        $active->switchTo(null);

        $this->assertNull($active->current($everything), 'All workspaces is null: the scope is omitted.');
    }

    public function testASessionSayingAllIsRefusedOnceAWorkspaceIsTakenAway(): void
    {
        // Written while they held everything, read after they stopped. The
        // check is made again rather than trusted, because "all" is the one
        // value that turns the scope off.
        $session = new ArraySessionStore();
        $session->set('ra.workspace', 'ra.all');

        $this->assertSame(
            'cyklobazar',
            $this->workspace($session)->current(Access::user(workspaces: ['cyklobazar', 'motobazar']))?->name,
            'A stale "all workspaces" survived losing a workspace, which would show every tenant\'s rows.',
        );
    }

    // --- the third state ---

    public function testNothingChosenIsAutomatic(): void
    {
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        self::assertTrue($this->workspace()->isAutomatic($user));
    }

    public function testChoosingAWorkspaceIsNotAutomatic(): void
    {
        $active = $this->workspace();
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $active->switchTo($this->workspaces()->get('motobazar'));

        self::assertFalse($active->isAutomatic($user));
    }

    public function testChoosingAllWorkspacesIsNotAutomaticEither(): void
    {
        // Both answer null from `current()`, and they are not the same state:
        // one is a decision and the other is the absence of one. A switcher
        // that could not tell them apart would mark the wrong entry.
        $active = $this->workspace();
        $everything = Access::user(workspaces: ['cyklobazar', 'motobazar', 'lodbazar']);

        $active->switchTo(null);

        self::assertNull($active->current($everything));
        self::assertFalse($active->isAutomatic($everything));
    }

    public function testGoingBackToAutomaticForgetsTheChoice(): void
    {
        // The reason this method exists: a choice is sticky and nobody can
        // un-choose by choosing. Somebody who switched to motobazar has no
        // other way back to "whatever this admin would have picked".
        $session = new ArraySessionStore();
        $active = $this->workspace($session);
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $active->switchTo($this->workspaces()->get('motobazar'));
        $active->automatic();

        self::assertTrue($active->isAutomatic($user));
        self::assertSame('cyklobazar', $active->current($user)?->name);
        self::assertNull($session->get('ra.workspace'), 'Going back to automatic left the choice stored.');
    }

    public function testGoingBackToAutomaticIsVisibleWithinTheSameRequest(): void
    {
        $active = $this->workspace();
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $active->switchTo($this->workspaces()->get('motobazar'));
        self::assertSame('motobazar', $active->current($user)?->name);

        $active->automatic();

        self::assertSame(
            'cyklobazar',
            $active->current($user)?->name,
            'The memo survived going back to automatic, so the switch did not take effect.',
        );
    }

    public function testSomebodyHoldingOneWorkspaceIsAlwaysAutomatic(): void
    {
        // Nothing to choose, so nothing can have been chosen. Marking their
        // one workspace as a decision would be the switcher claiming they made
        // one.
        self::assertTrue($this->workspace()->isAutomatic(Access::user(workspaces: ['cyklobazar'])));
    }

    public function testAStaleChoiceReadsAsAutomatic(): void
    {
        // It is ignored by `current()`, so the switcher must not mark it
        // either: the two have to agree about what the session means.
        $session = new ArraySessionStore();
        $session->set('ra.workspace', 'deleted_last_week');

        self::assertTrue(
            $this->workspace($session)->isAutomatic(Access::user(workspaces: ['cyklobazar', 'motobazar'])),
        );
    }

    // --- nobody, and nothing configured ---

    public function testAUserHoldingNoWorkspaceGetsNull(): void
    {
        // Null here is "nothing to scope by", and it is the Kernel that turns
        // that into "you can see nothing" -- told apart one layer up, where
        // whether any workspace is configured at all is also known.
        $this->assertNull($this->workspace()->current(Access::user(workspaces: [])));
    }

    public function testNobodyGetsNull(): void
    {
        $this->assertNull($this->workspace()->current(null));
    }

    public function testNoWorkspacesConfiguredGetsNull(): void
    {
        $active = new SessionWorkspace(new ArraySessionStore(), Workspaces::none());

        $this->assertNull($active->current(Access::user(workspaces: ['cyklobazar'])));
    }

    // --- harness ---

    private function workspace(?SessionStore $session = null): SessionWorkspace
    {
        return new SessionWorkspace($session ?? new ArraySessionStore(), $this->workspaces());
    }

    private function workspaces(): Workspaces
    {
        return Workspaces::fromConfig([
            // Declared in this order on purpose: the first one a user holds
            // is the answer, and `cyklobazar` being first is what the tests
            // above are asserting against.
            'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => ['ads']],
            'motobazar' => ['vars' => ['tenant_id' => 2], 'pages' => ['ads']],
            'lodbazar' => ['vars' => ['tenant_id' => 3], 'pages' => ['ads']],
        ], $this->pages());
    }

    private function pages(): PageRepository
    {
        if (!isset($this->root)) {
            $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-active-' . bin2hex(random_bytes(6));
            mkdir($this->root . '/pages', 0o777, true);
            file_put_contents(
                $this->root . '/pages/ads.php',
                "<?php\n\nreturn ['title' => 'Ads', 'entity' => ['table' => 'ra_test_ads'], "
                . "'regions' => ['grid' => ['type' => 'list', 'columns' => ['id' => []]]]];\n",
            );
        }

        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    private string $root;

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            @unlink($this->root . '/pages/ads.php');
            @rmdir($this->root . '/pages');
            @rmdir($this->root);
        }
    }
}
