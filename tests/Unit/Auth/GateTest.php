<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Permission;
use RockAdmin\Auth\Roles;
use RockAdmin\Auth\RouteAccess;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Loader;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;
use RockAdmin\Page\PageRepository;

/**
 * Step 7: may this user do this.
 *
 * The test that matters here is the structural one — every route the router
 * declares has an entry in the gate's table. A route added later without one
 * fails this suite rather than shipping open, which is the difference between
 * a gate and a habit.
 */
#[CoversClass(Gate::class)]
#[CoversClass(RouteAccess::class)]
final class GateTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-gate-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);

        $this->writePage('ads', true);
        $this->writePage('reports', false);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    // ------------------------------------------------------- the one that matters

    /**
     * Every route the router declares is either granted a permission, open to
     * anonymous callers, open to any signed-in user, or explicitly refused.
     *
     * Asserted over `Router::routeNames()` rather than over a list written
     * here, so adding a route without deciding who may reach it fails.
     */
    public function testEveryDeclaredRouteHasAnEntryInTheGatesTable(): void
    {
        $missing = [];

        foreach (Router::routeNames() as $name) {
            if (!Gate::declares($name)) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Routes with no access decision: ' . implode(', ', $missing));
    }

    /** And nothing in the gate's table names a route that no longer exists. */
    public function testTheGateDeclaresNoRouteTheRouterDoesNotHave(): void
    {
        $this->assertSame([], array_values(array_diff(Gate::routeNames(), Router::routeNames())));
    }

    public function testRouterExposesEveryRouteNameItMatches(): void
    {
        $names = Router::routeNames();

        $this->assertContains('page.index', $names);
        $this->assertContains('action', $names);
        $this->assertContains('assets', $names);
        $this->assertSame($names, array_values(array_unique($names)));
    }

    // ------------------------------------------------------- route to permission

    /** @return iterable<string, array{Route, string}> */
    public static function routePermissions(): iterable
    {
        yield 'index wants view' => [new Route('page.index', ['page' => 'ads']), 'ads.view'];
        yield 'detail wants view' => [new Route('page.detail', ['page' => 'ads', 'id' => '1']), 'ads.view'];
        yield 'region wants view' => [new Route('region', ['page' => 'ads', 'region' => 'grid']), 'ads.view'];
        yield 'create wants create' => [new Route('page.create', ['page' => 'ads']), 'ads.create'];
        yield 'edit wants update' => [new Route('page.edit', ['page' => 'ads', 'id' => '1']), 'ads.update'];
        // Copy saves as a new row, so it asks for the permission its own
        // submit needs -- otherwise update-without-create opens a form whose
        // only button answers 403.
        yield 'copy wants create' => [new Route('page.copy', ['page' => 'ads', 'id' => '1']), 'ads.create'];
        yield 'action create' => [new Route('action', ['page' => 'ads', 'action' => 'create']), 'ads.create'];
        yield 'action update' => [new Route('action', ['page' => 'ads', 'action' => 'update']), 'ads.update'];
        yield 'action delete' => [new Route('action', ['page' => 'ads', 'action' => 'delete']), 'ads.delete'];
    }

    #[DataProvider('routePermissions')]
    public function testARouteNamesThePermissionItNeeds(Route $route, string $expected): void
    {
        $this->assertSame($expected, Gate::permissionFor($route));
    }

    public function testARouteThatNeedsOnlyIdentityNamesNoPermission(): void
    {
        $this->assertNull(Gate::permissionFor(new Route('dashboard')));
        $this->assertNull(Gate::permissionFor(new Route('logout')));
    }

    public function testAnUndeclaredRouteIsRefusedRatherThanAllowedThrough(): void
    {
        $this->expectException(ForbiddenException::class);

        Gate::permissionFor(new Route('something.nobody.declared'));
    }

    public function testAnActionVerbNobodyDeclaredAPermissionForIsRefused(): void
    {
        try {
            Gate::permissionFor(new Route('action', ['page' => 'ads', 'action' => 'publish']));
            $this->fail('An undeclared action verb was not refused.');
        } catch (ForbiddenException $e) {
            $this->assertStringContainsString('publish', $e->getMessage());
            $this->assertSame(403, $e->status);
        }
    }

    public function testAccessForTellsAnonymousRoutesApartFromTheRest(): void
    {
        $this->assertSame(RouteAccess::Anonymous, Gate::accessFor('login.form'));
        $this->assertSame(RouteAccess::Anonymous, Gate::accessFor('login.submit'));
        $this->assertSame(RouteAccess::Anonymous, Gate::accessFor('assets'));
        $this->assertSame(RouteAccess::Authenticated, Gate::accessFor('dashboard'));
        $this->assertSame(RouteAccess::Denied, Gate::accessFor('diagnostics'));
    }

    // ------------------------------------------------------- allows()

    public function testAllowsAnswersFromTheRoleConfiguration(): void
    {
        $gate = $this->gate(['editor' => ['permissions' => ['ads.*', '!ads.delete']]]);
        $user = $this->user(['editor']);

        $this->assertTrue($gate->allows($user, 'ads.view'));
        $this->assertTrue($gate->allows($user, 'ads.update'));
        $this->assertFalse($gate->allows($user, 'ads.delete'));
    }

    public function testAnAnonymousUserIsAllowedNothing(): void
    {
        $this->assertFalse($this->gate(['god' => ['permissions' => ['*']]])->allows(null, 'ads.view'));
    }

    public function testAPermissionNobodyGeneratedIsDeniedEvenToAStar(): void
    {
        $gate = $this->gate(['god' => ['permissions' => ['*']]]);

        $this->assertFalse($gate->allows($this->user(['god']), 'ads.publish'));
        $this->assertFalse($gate->allows($this->user(['god']), 'reports.delete'));
    }

    // ------------------------------------------------------- authorize()

    public function testAuthorizeLetsAPermittedRouteThrough(): void
    {
        $gate = $this->gate(['editor' => ['permissions' => ['ads.*']]]);

        $gate->authorize($this->user(['editor']), new Route('page.index', ['page' => 'ads']));

        $this->expectNotToPerformAssertions();
    }

    public function testAuthorizeRefusesAMissingPermissionWith403(): void
    {
        $gate = $this->gate(['viewer' => ['permissions' => ['*.view']]]);

        try {
            $gate->authorize($this->user(['viewer']), new Route('action', ['page' => 'ads', 'action' => 'delete']));
            $this->fail('A viewer was allowed to delete.');
        } catch (ForbiddenException $e) {
            $this->assertSame(403, $e->status);
            $this->assertStringContainsString('ads.delete', $e->getMessage());
        }
    }

    public function testAuthorizeRefusesADeniedRouteForEverybody(): void
    {
        $gate = $this->gate(['god' => ['permissions' => ['*']]]);

        $this->expectException(ForbiddenException::class);

        $gate->authorize($this->user(['god']), new Route('diagnostics'));
    }

    public function testAuthorizeAllowsARouteThatNeedsOnlyIdentity(): void
    {
        $gate = $this->gate(['viewer' => ['permissions' => []]]);

        $gate->authorize($this->user(['viewer']), new Route('dashboard'));

        $this->expectNotToPerformAssertions();
    }

    // ------------------------------------------------------- roles are built eagerly

    /**
     * `Roles::fromConfig()` is the only thing that validates `roles.php`, and
     * `Loader` cannot call it — it has no page repository. So if nobody builds
     * the roles, a malformed file is never read: the gate then grants whatever
     * an empty configuration grants, which is nothing, with no explanation
     * anywhere. `Gate::fromConfig()` is that caller, and wiring it into a
     * request is what makes the refusal actually happen.
     */
    public function testAMalformedRolesFileFailsARequestRatherThanSittingThereUnread(): void
    {
        file_put_contents(
            $this->root . '/roles.php',
            "<?php\n\nreturn ['editor' => ['permissions' => ['nosuchpage.view']]];\n",
        );

        try {
            $this->gateFromDisk();
            $this->fail('A role naming a page that does not exist was accepted.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString('nosuchpage', $e->getMessage());
            $this->assertStringContainsString('editor', $e->getMessage());
        }
    }

    public function testAWellFormedRolesFileOnDiskProducesAWorkingGate(): void
    {
        file_put_contents(
            $this->root . '/roles.php',
            "<?php\n\nreturn ['editor' => ['permissions' => ['ads.*']]];\n",
        );

        $gate = $this->gateFromDisk();

        $this->assertTrue($gate->allows($this->user(['editor']), 'ads.update'));
        $this->assertFalse($gate->allows($this->user(['editor']), 'reports.view'));
    }

    // ------------------------------------------------------- fixtures

    /** @param list<string> $roles */
    private function user(array $roles): AuthUser
    {
        return new AuthUser('7', 'jana@example.test', 'Jana', $roles);
    }

    /** @param array<string, mixed> $roles */
    private function gate(array $roles): Gate
    {
        return new Gate(Roles::fromConfig($roles, Permission::catalogue($this->pages())));
    }

    /** Wired the way a host wires it: a loaded configuration and a page repository. */
    private function gateFromDisk(): Gate
    {
        $env = static fn (string $name): ?string => null;

        return Gate::fromConfig((new Loader($this->root, $env))->load(), $this->pages());
    }

    private function pages(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    private function writePage(string $name, bool $writable): void
    {
        $form = $writable
            ? "'form' => ['type' => 'form', 'form' => ['fields' => ['title' => ['type' => 'text']]]],"
            : '';

        $body = "<?php\n\nreturn [\n"
            . "    'title' => '{$name}',\n"
            . "    'entity' => ['table' => '{$name}'],\n"
            . "    'regions' => [\n"
            . "        'grid' => [\n"
            . "            'type' => 'list',\n"
            . "            'columns' => ['id' => ['source' => 'id'], 'title' => ['source' => 'title']],\n"
            . "        ],\n"
            . "        {$form}\n"
            . "    ],\n"
            . "];\n";

        file_put_contents($this->root . '/pages/' . $name . '.php', $body);
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
