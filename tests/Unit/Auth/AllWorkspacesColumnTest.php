<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;

/**
 * The column a grid gains when it is showing every workspace at once.
 *
 * Specification 9.3: "the scope is omitted and the grid automatically adds a
 * workspace column so rows remain attributable". A grid showing two tenants'
 * rows with nothing saying which is which is worse than no "all" at all —
 * it is the same page, with twice the rows, and no way to tell them apart.
 */
#[CoversNothing]
final class AllWorkspacesColumnTest extends TestCase
{
    public function testReadingEveryWorkspaceAtOnceIsWhatAddsIt(): void
    {
        $this->assertTrue($this->scope(all: true)->isAcrossAllWorkspaces($this->page()));
        $this->assertFalse($this->scope()->isAcrossAllWorkspaces($this->page()));
    }

    public function testAPageWhoseRowsAreNotDividedNeverGainsIt(): void
    {
        // A table of categories is the same in every workspace. Adding a
        // column saying so would be a column of one repeated value.
        $this->assertFalse($this->scope(all: true)->isAcrossAllWorkspaces($this->page('shared')));
    }

    public function testTheColumnIsTheOneThePageScopesOn(): void
    {
        // Whatever a page scopes on is by definition what divides its rows.
        // Inventing a second convention -- a `workspace_column` key, a guess
        // at `tenant_id` -- would be a second thing to keep in agreement with
        // the first.
        $this->assertSame('tenant_id', $this->scope(all: true)->workspaceColumn($this->page()));
        $this->assertNull($this->scope(all: true)->workspaceColumn($this->page('shared')));
    }

    public function testAValueBecomesTheWorkspacesLabel(): void
    {
        // The label, not the raw value. A grid saying "2" leaves whoever
        // reads it to remember which tenant 2 is, which is exactly the
        // attributability 9.3 asks for and does not get.
        $scope = $this->scope(all: true);

        $this->assertSame('Cyklobazar.cz', $scope->workspaceLabel($this->page(), 1));
        $this->assertSame('Motobazar.cz', $scope->workspaceLabel($this->page(), 2));
    }

    public function testAValueIsMatchedAsTextAcrossDrivers(): void
    {
        // One driver hands back `1` where another hands back `'1'`. A grid
        // that said "2" on PostgreSQL and "Motobazar.cz" on MySQL would be a
        // rule-3 bug nobody would think to look for.
        $scope = $this->scope(all: true);

        $this->assertSame('Motobazar.cz', $scope->workspaceLabel($this->page(), '2'));
        $this->assertSame($scope->workspaceLabel($this->page(), 2), $scope->workspaceLabel($this->page(), '2'));
    }

    public function testAValueNoWorkspaceClaimsComesBackAsItself(): void
    {
        // Rows outlive the workspace they were written in. "7" is more use to
        // whoever is looking at it than an empty cell.
        $this->assertSame('7', $this->scope(all: true)->workspaceLabel($this->page(), 7));
    }

    // --- harness ---

    private function scope(bool $all = false): RequestScope
    {
        $session = new ArraySessionStore();

        if ($all) {
            $session->set('ra.workspace', 'ra.all');
        }

        $workspaces = $this->workspaces();

        return new RequestScope(
            new SessionWorkspace($session, $workspaces),
            Access::identityFor(Access::user(workspaces: ['cyklobazar', 'motobazar'])),
            $workspaces,
        );
    }

    private function workspaces(): Workspaces
    {
        return Workspaces::fromConfig([
            'cyklobazar' => ['label' => 'Cyklobazar.cz', 'vars' => ['tenant_id' => 1], 'pages' => '@all'],
            'motobazar' => ['label' => 'Motobazar.cz', 'vars' => ['tenant_id' => 2], 'pages' => '@all'],
        ], $this->pages());
    }

    private function page(string $name = 'scoped'): PageDefinition
    {
        return $this->pages()->get($name);
    }

    private function pages(): PageRepository
    {
        if (!isset($this->root)) {
            $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-allws-' . bin2hex(random_bytes(6));
            mkdir($this->root . '/pages', 0o777, true);

            $region = "'regions' => ['grid' => ['type' => 'list', 'columns' => ['id' => []]]]";

            file_put_contents(
                $this->root . '/pages/scoped.php',
                "<?php\n\nreturn ['title' => 'Scoped', 'entity' => ['table' => 'ra_test_ads', "
                . "'scope' => ['tenant_id' => '{{workspace.tenant_id}}']], {$region}];\n",
            );
            file_put_contents(
                $this->root . '/pages/shared.php',
                "<?php\n\nreturn ['title' => 'Shared', 'entity' => ['table' => 'ra_test_ads'], {$region}];\n",
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
            foreach ((array) glob($this->root . '/pages/*.php') as $file) {
                @unlink((string) $file);
            }

            @rmdir($this->root . '/pages');
            @rmdir($this->root);
        }
    }
}
