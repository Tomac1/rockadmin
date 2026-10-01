<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\WorkspaceDefinition;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Enums;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;

/**
 * `workspaces.php`, read and refused.
 *
 * Specification 9.3. The decisions this file pins are the two that a later
 * reader would otherwise have to guess at, and both of them are about what
 * happens when somebody forgets something.
 */
#[CoversClass(Workspaces::class)]
#[CoversClass(WorkspaceDefinition::class)]
final class WorkspaceSchemaTest extends TestCase
{
    public function testNoWorkspacesFileMeansNothingIsScoped(): void
    {
        // The single-tenant case, and it has to stay free. An admin over one
        // database with one site should not pay for a subsystem it is not
        // using; the day it grows a second site it adds one file.
        $workspaces = Workspaces::fromConfig([], $this->pages());

        $this->assertFalse($workspaces->configured());
        $this->assertSame([], $workspaces->names());
        $this->assertFalse($workspaces->userHoldsEvery(Access::identity()->user()));
    }

    public function testAWorkspaceCarriesItsLabelVarsAndPages(): void
    {
        $workspaces = $this->build([
            'cyklobazar' => [
                'label' => 'Cyklobazar.cz',
                'vars' => ['domain' => 'cyklobazar.cz', 'tenant_id' => 1],
                'pages' => ['ads'],
            ],
        ]);

        $one = $workspaces->get('cyklobazar');

        $this->assertSame('Cyklobazar.cz', $one->label);
        $this->assertSame(1, $one->var('tenant_id'));
        $this->assertSame('cyklobazar.cz', $one->var('domain'));
        $this->assertTrue($one->shows('ads'));
        $this->assertFalse($one->shows('users'));
    }

    public function testAMissingLabelFallsBackToTheName(): void
    {
        $this->assertSame('cyklobazar', $this->build([
            'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => ['ads']],
        ])->get('cyklobazar')->label);
    }

    // --- the two decisions ---

    public function testAWorkspaceListingNoPagesIsRefusedRatherThanMeaningAll(): void
    {
        // The decision the milestone plan left open, and the reason it goes
        // this way: the two readings are "visible everywhere" and "visible
        // nowhere", and the cost of being wrong is not symmetric. Under the
        // first, forgetting to list a page silently shows one tenant's page
        // to every other tenant. Under this one it makes the page vanish,
        // which somebody notices within a minute.
        //
        // So neither is a default: an omitted `pages` is refused outright and
        // `'@all'` says "every page" out loud.
        try {
            $this->build(['cyklobazar' => ['vars' => ['tenant_id' => 1]]]);
            $this->fail('A workspace listing no pages was accepted.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("'pages'", $e->getMessage());
            $this->assertStringContainsString('@all', $e->getMessage());
        }
    }

    public function testEveryPageIsSpeltOutRatherThanImplied(): void
    {
        $all = $this->build([
            'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => '@all'],
        ])->get('cyklobazar');

        $this->assertNull($all->pages);
        $this->assertTrue($all->shows('ads'));
        $this->assertTrue($all->shows('users'));

        // Including a page added after the file was last edited, which is the
        // whole point of saying it rather than listing today's pages.
        $this->assertTrue($all->shows('anything_added_later'));
    }

    public function testAnEmptyPagesListIsRefused(): void
    {
        // Legal to write and impossible to mean: a workspace showing nothing
        // is one nobody in it could do anything in.
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('shows nothing at all');

        $this->build(['cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => []]]);
    }

    public function testAPageThatDoesNotExistNamesTheNearest(): void
    {
        try {
            $this->build(['cyklobazar' => ['vars' => [], 'pages' => ['adz']]]);
            $this->fail('A workspace showing a page that does not exist was accepted.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("'adz'", $e->getMessage());
            $this->assertStringContainsString("Did you mean 'ads'", $e->getMessage());
        }
    }

    // --- variables are values, not SQL ---

    public function testAVariableThatIsNotAValueIsRefused(): void
    {
        // 9.3's own rule: "workspace variables are values, not SQL".
        // A value becomes a bound parameter and can only ever be a parameter;
        // anything else would have to be interpolated, and interpolating
        // configuration into a WHERE clause is what this arrangement exists
        // to make impossible.
        try {
            $this->build([
                'cyklobazar' => ['vars' => ['tenant_id' => ['1', '2']], 'pages' => ['ads']],
            ]);
            $this->fail('A workspace variable that is not a value was accepted.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString('values, not SQL', $e->getMessage());
            $this->assertStringContainsString("'tenant_id'", $e->getMessage());
        }
    }

    public function testAVariableTheWorkspaceDoesNotDeclareIsNull(): void
    {
        // Null is "this workspace does not declare it", which is a different
        // thing from a variable whose value is empty: the first is a
        // configuration mistake and the second is data. Whoever binds a
        // placeholder tells them apart.
        $one = $this->build([
            'cyklobazar' => ['vars' => ['tenant_id' => 1, 'blank' => ''], 'pages' => ['ads']],
        ])->get('cyklobazar');

        $this->assertNull($one->var('nothing'));
        $this->assertSame('', $one->var('blank'));
    }

    // --- names, and who holds what ---

    public function testAWorkspaceNameIsAddressableInAUrl(): void
    {
        // It is one: POST /w/{workspace}. A name that is not a path segment
        // is a switcher nobody can press.
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('POST /w/');

        $this->build(['Cyklo Bazar' => ['vars' => [], 'pages' => ['ads']]]);
    }

    public function testAUserSeesOnlyTheWorkspacesTheyHold(): void
    {
        $workspaces = $this->two();
        $user = Access::user(workspaces: ['cyklobazar']);

        $this->assertSame(
            ['cyklobazar'],
            array_map(static fn (WorkspaceDefinition $w): string => $w->name, $workspaces->forUser($user)),
        );
    }

    public function testHoldingEveryWorkspaceIsWhatUnlocksAllWorkspaces(): void
    {
        // 9.3's condition on the "all workspaces" option: it is offered only
        // to somebody who could reach every row through the switcher anyway,
        // so choosing it grants nothing they did not already have. Two of
        // three is a view with a boundary in it and no way to see where.
        $workspaces = $this->two();

        $this->assertTrue($workspaces->userHoldsEvery(Access::user(workspaces: ['cyklobazar', 'motobazar'])));
        $this->assertFalse($workspaces->userHoldsEvery(Access::user(workspaces: ['cyklobazar'])));
        $this->assertFalse($workspaces->userHoldsEvery(null));
    }

    public function testAWorkspaceTheUserHoldsButNobodyConfiguresIsIgnored(): void
    {
        // A workspace can be removed from the file while users still carry
        // its name. Refusing to let those people in at all would turn a
        // tidy-up into an outage.
        $workspaces = $this->two();

        $this->assertSame(
            ['cyklobazar'],
            array_map(
                static fn (WorkspaceDefinition $w): string => $w->name,
                $workspaces->forUser(Access::user(workspaces: ['cyklobazar', 'gone'])),
            ),
        );
    }

    public function testAnUnknownWorkspaceNamesTheNearest(): void
    {
        try {
            $this->two()->get('cyklobazr');
            $this->fail('An unknown workspace was returned.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("Did you mean 'cyklobazar'", $e->getMessage());
        }
    }

    // --- harness ---

    private function two(): Workspaces
    {
        return $this->build([
            'cyklobazar' => ['label' => 'Cyklobazar.cz', 'vars' => ['tenant_id' => 1], 'pages' => ['ads']],
            'motobazar' => ['label' => 'Motobazar.cz', 'vars' => ['tenant_id' => 2], 'pages' => ['ads']],
        ]);
    }

    /** @param array<string, mixed> $raw */
    private function build(array $raw): Workspaces
    {
        return Workspaces::fromConfig($raw, $this->pages());
    }

    private function pages(): PageRepository
    {
        if (!isset($this->root)) {
            $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-workspaces-' . bin2hex(random_bytes(6));
            mkdir($this->root . '/pages', 0o777, true);

            foreach (['ads', 'users'] as $name) {
                file_put_contents(
                    $this->root . '/pages/' . $name . '.php',
                    "<?php\n\nreturn ['title' => '{$name}', 'entity' => ['table' => 'ra_test_{$name}'], "
                    . "'regions' => ['grid' => ['type' => 'list', 'columns' => ['id' => []]]]];\n",
                );
            }
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
            foreach ((array) glob($this->root . '/pages/*.php') as $file) {
                @unlink((string) $file);
            }

            @rmdir($this->root . '/pages');
            @rmdir($this->root);
        }
    }
}
