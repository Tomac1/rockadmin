<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Http\WorkspaceHandler;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;

/**
 * Switching workspace.
 *
 * Specification 9.3 for what may be switched to, and 5.1 for why it is a
 * POST: switching changes what somebody sees for the rest of the session,
 * and browsers prefetch links on hover — a GET would let a hover over the
 * switcher quietly change the active workspace.
 *
 * The refusals are all the same refusal from different sides. You may switch
 * to a workspace you hold and to nothing else; the switcher does not offer
 * the others, and this does not trust the switcher, because the URL is
 * typeable and the body is editable.
 */
#[CoversClass(WorkspaceHandler::class)]
final class WorkspaceSwitchTest extends TestCase
{
    public function testSwitchingToAWorkspaceYouHoldIsRemembered(): void
    {
        $session = new ArraySessionStore();
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $response = $this->switch($session, $user, 'motobazar');

        $this->assertSame(302, $response->status);
        $this->assertSame('motobazar', $this->active($session, $user)?->name);
    }

    public function testItComesBackToThePageYouWereOn(): void
    {
        // Switching reloads the page you were looking at, in the new
        // workspace, which is the useful behaviour -- the grid changes
        // underneath you rather than dumping you on the dashboard.
        $session = new ArraySessionStore();
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $response = $this->switch($session, $user, 'motobazar', ['_ret' => '/admin/p/ads']);

        $this->assertSame('/admin/p/ads', $response->headers['location'] ?? null);
    }

    public function testAReturnAddressOffThisSiteIsIgnored(): void
    {
        // It arrives from the browser and is no more trustworthy here than a
        // form's `_ret` is, so it goes through the same validation.
        $session = new ArraySessionStore();
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $response = $this->switch($session, $user, 'motobazar', ['_ret' => 'https://example.com/']);

        $this->assertSame('/admin/', $response->headers['location'] ?? null);
    }

    // --- what may not be switched to ---

    public function testSwitchingToAWorkspaceYouDoNotHoldIsRefused(): void
    {
        $session = new ArraySessionStore();
        $user = Access::user(workspaces: ['cyklobazar']);

        try {
            $this->switch($session, $user, 'motobazar');
            $this->fail('A workspace the user does not hold was switched to.');
        } catch (ForbiddenException $e) {
            $this->assertStringContainsString('motobazar', $e->getMessage());
        }

        $this->assertSame('cyklobazar', $this->active($session, $user)?->name);
    }

    public function testAWorkspaceThatDoesNotExistIsRefusedTheSameWay(): void
    {
        // One refusal for both, deliberately. Telling somebody that a
        // workspace exists but is not theirs is telling them what their
        // employer's other tenants are called.
        $session = new ArraySessionStore();
        $user = Access::user(workspaces: ['cyklobazar']);

        $missing = $this->refusalFor($session, $user, 'no_such_place');
        $theirs = $this->refusalFor($session, $user, 'motobazar');

        $this->assertSame(
            str_replace('motobazar', 'X', $theirs),
            str_replace('no_such_place', 'X', $missing),
            'The two refusals differ, so one of them says which workspaces exist.',
        );
    }

    public function testSwitchingWithNoTokenIsRefused(): void
    {
        $session = new ArraySessionStore();

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('CSRF');

        $this->handler($session)->handle(
            new Route('workspace.switch', ['workspace' => 'motobazar']),
            new Request('POST', 'w/motobazar', body: []),
        );
    }

    // --- all workspaces ---

    public function testAllWorkspacesNeedsEveryWorkspace(): void
    {
        $session = new ArraySessionStore();

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('all of them');

        $this->switch($session, Access::user(workspaces: ['cyklobazar']), WorkspaceHandler::ALL);
    }

    public function testSomebodyHoldingEveryWorkspaceMaySwitchToAllOfThem(): void
    {
        $session = new ArraySessionStore();
        $user = Access::user(workspaces: ['cyklobazar', 'motobazar']);

        $this->switch($session, $user, WorkspaceHandler::ALL);

        $this->assertNull($this->active($session, $user), 'All workspaces is null: the scope is omitted.');
    }

    public function testTheAllSentinelCannotCollideWithARealWorkspace(): void
    {
        // `Workspaces` refuses a name that is not `^[a-z][a-z0-9_]*$`, so a
        // leading underscore cannot be one however an installation names its
        // tenants. A sentinel that could be a real name would make "all
        // workspaces" unreachable for whoever named a workspace `all`, and
        // nobody would ever work out why.
        $this->assertStringStartsWith('_', WorkspaceHandler::ALL);
        $this->assertFalse($this->workspaces()->has(WorkspaceHandler::ALL));
    }

    // --- harness ---

    /** @param array<string, mixed> $body */
    private function switch(SessionStore $session, AuthUser $user, string $name, array $body = []): Response
    {
        return $this->handler($session, $user)->handle(
            new Route('workspace.switch', ['workspace' => $name]),
            new Request('POST', "w/{$name}", body: [Csrf::FIELD => (new Csrf($session))->token()] + $body),
        );
    }

    private function refusalFor(SessionStore $session, AuthUser $user, string $name): string
    {
        try {
            $this->switch($session, $user, $name);
        } catch (ForbiddenException $e) {
            return $e->getMessage();
        }

        $this->fail("Switching to '{$name}' was allowed.");
    }

    private function handler(SessionStore $session, ?AuthUser $user = null): WorkspaceHandler
    {
        return new WorkspaceHandler(
            $this->workspaces(),
            new SessionWorkspace($session, $this->workspaces()),
            Access::identityFor($user ?? Access::user(workspaces: ['cyklobazar', 'motobazar'])),
            new Csrf($session),
            new UrlGenerator('/admin'),
        );
    }

    private function active(SessionStore $session, AuthUser $user): ?\RockAdmin\Auth\WorkspaceDefinition
    {
        return (new SessionWorkspace($session, $this->workspaces()))->current($user);
    }

    private function workspaces(): Workspaces
    {
        return Workspaces::fromConfig([
            'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => '@all'],
            'motobazar' => ['vars' => ['tenant_id' => 2], 'pages' => '@all'],
        ], $this->pages());
    }

    private function pages(): PageRepository
    {
        if (!isset($this->root)) {
            $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-switch-' . bin2hex(random_bytes(6));
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
