<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\WorkspaceDefinition;
use RockAdmin\Config\Binder;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Placeholder;
use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\Query;
use RockAdmin\Db\QueryBuilder;
use RockAdmin\Tests\Support\Access;

/**
 * Binding the placeholders that survived configuration.
 *
 * `{{workspace.tenant_id}}` cannot be baked into a cached configuration, so
 * `Resolver` leaves it as an object and this is where it stops being one. The
 * refusals matter more than the successes: a placeholder that bound to null
 * would scope a query to `WHERE tenant_id IS NULL`, which is a silently empty
 * grid, and a scope quietly dropped is every tenant's rows at once.
 */
#[CoversClass(Binder::class)]
final class BinderTest extends TestCase
{
    public function testAWorkspaceVariableBecomesItsValue(): void
    {
        $bound = $this->binder()->scope(['tenant_id' => new Placeholder('workspace', 'tenant_id')]);

        $this->assertSame(['tenant_id' => 1], $bound);
    }

    public function testAUserPlaceholderBecomesTheSignedInUsersValue(): void
    {
        $bound = $this->binder()->scope(['owner_id' => new Placeholder('user', 'id')]);

        $this->assertSame(['owner_id' => '7'], $bound);
    }

    public function testAPlainValueInAScopeIsLeftAlone(): void
    {
        // A scope may be a literal — `'deleted_at' => null` or `'kind' => 'ad'`
        // — and those are not placeholders and are not this class's business.
        $bound = $this->binder()->scope(['kind' => 'ad', 'tenant_id' => new Placeholder('workspace', 'tenant_id')]);

        $this->assertSame(['kind' => 'ad', 'tenant_id' => 1], $bound);
    }

    public function testEveryValueTypeAVariableMayHoldSurvives(): void
    {
        $binder = $this->binder(vars: ['n' => 0, 's' => '', 'b' => false, 'f' => 1.5]);

        $this->assertSame(
            ['a' => 0, 'b' => '', 'c' => false, 'd' => 1.5],
            $binder->scope([
                'a' => new Placeholder('workspace', 'n'),
                'b' => new Placeholder('workspace', 's'),
                'c' => new Placeholder('workspace', 'b'),
                'd' => new Placeholder('workspace', 'f'),
            ]),
            'A falsy value must bind as itself: tenant 0 and the empty string are data, not absence.',
        );
    }

    // --- what must not bind ---

    public function testAVariableTheWorkspaceHasNotGotIsRefused(): void
    {
        // Not null. `WHERE tenant_id IS NULL` is an empty grid with no
        // visible cause, and whoever wrote the scope would be looking at the
        // page wondering where the rows went.
        try {
            $this->binder()->scope(['tenant_id' => new Placeholder('workspace', 'site_id')]);
            $this->fail('A placeholder naming no variable was bound.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString('{{workspace.site_id}}', $e->getMessage());
            $this->assertStringContainsString("'tenant_id'", $e->getMessage());
            $this->assertStringContainsString("declares: 'tenant_id'", $e->getMessage());
        }
    }

    public function testAWorkspacePlaceholderWithNoActiveWorkspaceIsRefused(): void
    {
        // Either nothing is configured while a page declares a scope, or the
        // scope should have been dropped for somebody working across all
        // workspaces. Both are mistakes one layer up, and binding anything
        // would hide them.
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('no workspace is active');

        $this->binder(workspace: null)->scope(['tenant_id' => new Placeholder('workspace', 'tenant_id')]);
    }

    public function testAUserPlaceholderWithNobodySignedInIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('nobody is signed in');

        $this->binder(user: false)->scope(['owner_id' => new Placeholder('user', 'id')]);
    }

    public function testAListOnTheUserIsNotAScope(): void
    {
        // Roles and workspaces are lists. A scope is one value compared to
        // one column, and stringifying a list into one would produce
        // something no WHERE clause could mean.
        foreach (['roles', 'workspaces'] as $name) {
            try {
                $this->binder()->scope(['x' => new Placeholder('user', $name)]);
                $this->fail("A user's {$name} was bound into a scope.");
            } catch (ConfigException $e) {
                $this->assertStringContainsString('lists', $e->getMessage());
            }
        }
    }

    public function testANamespaceNobodyBindsIsRefused(): void
    {
        // `env` and `config` resolve at load; reaching here means one
        // survived that it should not have.
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("'workspace', 'user'");

        $this->binder()->scope(['x' => new Placeholder('env', 'HOME')]);
    }

    // --- the backstop ---

    public function testAnUnboundPlaceholderStillCannotReachAQuery(): void
    {
        // `QueryBuilder::assertBound()` was written two milestones before
        // anything could bind a placeholder, against exactly this moment. It
        // is the backstop for everything this class gets wrong, and a test
        // proving it is still reachable is worth more than one proving the
        // happy path — which is why it is here rather than only in the query
        // builder's own file.
        $query = new Query(
            entity: new Entity('ra_test_ads'),
            columns: ['id' => 'id'],
            scope: ['tenant_id' => new Placeholder('workspace', 'tenant_id')],
            count: CountStrategy::None,
        );

        $this->expectException(DbException::class);
        $this->expectExceptionMessage('must be bound to the request');

        (new QueryBuilder(new MySqlDialect()))->rows($query);
    }

    // --- harness ---

    /**
     * @param array<string, bool|float|int|string> $vars
     * @param WorkspaceDefinition|null             $workspace null for "all workspaces", or none configured
     * @param bool                                 $user      false for nobody signed in
     */
    private function binder(
        array $vars = ['tenant_id' => 1],
        WorkspaceDefinition|false|null $workspace = false,
        bool $user = true,
    ): Binder {
        return new Binder(
            $workspace === false
                ? new WorkspaceDefinition('cyklobazar', 'Cyklobazar.cz', $vars, ['ads'])
                : $workspace,
            $user ? Access::user(workspaces: ['cyklobazar']) : null,
        );
    }
}
