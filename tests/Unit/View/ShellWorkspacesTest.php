<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Http\WorkspaceHandler;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;
use RockAdmin\View\ShellWorkspaces;
use RockAdmin\View\WorkspaceView;

/**
 * What the switcher offers, and which entry it says you are in.
 *
 * The second half is the part worth testing. There are three states and only
 * one of them may be marked: under automatic the workspace it happens to
 * resolve to is *not* marked, because a switcher showing two current entries
 * asks the person to work out which of them they are in — and the one they
 * would guess is the wrong one, since pressing the other changes nothing
 * visible and everything about what happens next.
 */
#[CoversClass(ShellWorkspaces::class)]
final class ShellWorkspacesTest extends TestCase
{
    private string $root;

    public function testNothingIsOfferedWhenThereIsNothingToChoose(): void
    {
        self::assertSame([], $this->switcher(['cyklobazar'])->all(), 'One workspace drew a switcher.');
        self::assertSame([], $this->switcher([])->all());
    }

    public function testAutomaticIsOfferedFirstAndSaysWhatItResolvesTo(): void
    {
        $views = $this->switcher(['cyklobazar', 'motobazar'])->all();

        self::assertSame(WorkspaceHandler::AUTOMATIC, $views[0]->key);
        self::assertSame('Automatic (Cyklobazar.cz)', $views[0]->label);
        self::assertTrue($views[0]->active, 'Nothing was chosen, so automatic is the current state.');
    }

    public function testUnderAutomaticTheWorkspaceItResolvesToIsNotMarked(): void
    {
        $views = $this->switcher(['cyklobazar', 'motobazar'])->all();

        self::assertSame(['_auto'], $this->marked($views), 'Two entries were marked as current.');
    }

    public function testChoosingAWorkspaceMarksThatOneAndNotAutomatic(): void
    {
        $session = new ArraySessionStore();
        $switcher = $this->switcher(['cyklobazar', 'motobazar'], $session);

        $this->active($session)->switchTo($this->workspaces()->get('motobazar'));

        self::assertSame(['motobazar'], $this->marked($switcher->all()));
    }

    public function testOnceSomethingIsChosenAutomaticNoLongerNamesAWorkspace(): void
    {
        // What automatic would answer instead is the resolver's business and
        // cannot be read from here. Naming the chosen workspace would be the
        // one label on this control that is actively wrong -- it would promise
        // that pressing it changes nothing, when it is how you change back.
        $session = new ArraySessionStore();
        $switcher = $this->switcher(['cyklobazar', 'motobazar'], $session);

        $this->active($session)->switchTo($this->workspaces()->get('motobazar'));

        self::assertSame('Automatic', $switcher->all()[0]->label);
    }

    public function testAllWorkspacesIsOfferedLastAndOnlyToSomebodyHoldingEveryOne(): void
    {
        $theirs = $this->switcher(['cyklobazar', 'motobazar'])->all();
        $every = $this->switcher(['cyklobazar', 'motobazar', 'lodbazar'])->all();

        self::assertSame(
            [WorkspaceHandler::AUTOMATIC, 'cyklobazar', 'motobazar'],
            array_map(static fn (WorkspaceView $v): string => $v->key, $theirs),
        );
        self::assertSame(
            [WorkspaceHandler::AUTOMATIC, 'cyklobazar', 'motobazar', 'lodbazar', WorkspaceHandler::ALL],
            array_map(static fn (WorkspaceView $v): string => $v->key, $every),
        );
    }

    public function testChoosingAllWorkspacesMarksAllWorkspaces(): void
    {
        $session = new ArraySessionStore();
        $switcher = $this->switcher(['cyklobazar', 'motobazar', 'lodbazar'], $session);

        $this->active($session)->switchTo(null);

        self::assertSame([WorkspaceHandler::ALL], $this->marked($switcher->all()));
        self::assertSame('Automatic', $switcher->all()[0]->label, 'Automatic claimed to resolve to a workspace.');
    }

    public function testGoingBackToAutomaticMarksAutomaticAgain(): void
    {
        $session = new ArraySessionStore();
        $switcher = $this->switcher(['cyklobazar', 'motobazar'], $session);
        $active = $this->active($session);

        $active->switchTo($this->workspaces()->get('motobazar'));
        $active->automatic();

        self::assertSame([WorkspaceHandler::AUTOMATIC], $this->marked($switcher->all()));
    }

    /**
     * @param  list<WorkspaceView> $views
     * @return list<string>
     */
    private function marked(array $views): array
    {
        $marked = [];

        foreach ($views as $view) {
            if ($view->active) {
                $marked[] = $view->key;
            }
        }

        return $marked;
    }

    /** @param list<string> $workspaces */
    private function switcher(array $workspaces, ?SessionStore $session = null): ShellWorkspaces
    {
        return new ShellWorkspaces(
            $this->workspaces(),
            $this->active($session ?? new ArraySessionStore()),
            Access::identityFor(Access::user(workspaces: $workspaces)),
            new UrlGenerator('/admin'),
        );
    }

    private function active(SessionStore $session): SessionWorkspace
    {
        return new SessionWorkspace($session, $this->workspaces());
    }

    private function workspaces(): Workspaces
    {
        return Workspaces::fromConfig([
            'cyklobazar' => ['label' => 'Cyklobazar.cz', 'vars' => ['tenant_id' => 1], 'pages' => ['ads']],
            'motobazar' => ['label' => 'Motobazar.cz', 'vars' => ['tenant_id' => 2], 'pages' => ['ads']],
            'lodbazar' => ['label' => 'Lodbazar.cz', 'vars' => ['tenant_id' => 3], 'pages' => ['ads']],
        ], $this->pages());
    }

    private function pages(): PageRepository
    {
        if (!isset($this->root)) {
            $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-switcher-' . bin2hex(random_bytes(6));
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
}
