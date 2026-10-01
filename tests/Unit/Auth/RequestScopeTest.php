<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\SessionWorkspace;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Enums;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\Access;

/**
 * The one object that answers "what is this request's scope".
 *
 * Four places ask it — the grid's query, the single-row read, the form, and
 * every write — and the reason it is one object is that four of them working
 * it out separately is four chances to differ, where the one that differs is
 * a hole rather than a bug.
 */
#[CoversClass(RequestScope::class)]
final class RequestScopeTest extends TestCase
{
    public function testAScopedPageIsBoundToTheActiveWorkspace(): void
    {
        $scope = $this->scope(['cyklobazar'])->forPage($this->page('scoped'));

        $this->assertSame(['tenant_id' => 1], $scope);
    }

    public function testADifferentWorkspaceGivesADifferentBoundary(): void
    {
        $scope = $this->scope(['motobazar'])->forPage($this->page('scoped'));

        $this->assertSame(['tenant_id' => 2], $scope);
    }

    public function testAPageWithNoScopeGetsNone(): void
    {
        // A page of countries or categories is shared: the same rows in every
        // workspace. That is a real arrangement and must not be turned into
        // an error by somebody who happens to be in no particular workspace.
        $this->assertSame([], $this->scope(['cyklobazar'])->forPage($this->page('shared')));
        $this->assertFalse($this->scope(['cyklobazar'])->isWorkspaceScoped($this->page('shared')));
    }

    public function testAllWorkspacesDropsTheWorkspaceHalfAndKeepsTheRest(): void
    {
        // The scope being omitted is what "all workspaces" means. But a page
        // scoped to both a workspace and the signed-in user still belongs to
        // that user, so only the workspace half goes.
        $session = new ArraySessionStore();
        $session->set('ra.workspace', 'ra.all');

        $scope = $this->scope(['cyklobazar', 'motobazar'], $session)->forPage($this->page('mine'));

        $this->assertSame(['owner_id' => '7'], $scope, 'All workspaces dropped more than the workspace.');
    }

    public function testAllWorkspacesLeavesAWorkspaceOnlyPageUnscoped(): void
    {
        $session = new ArraySessionStore();
        $session->set('ra.workspace', 'ra.all');

        $this->assertSame([], $this->scope(['cyklobazar', 'motobazar'], $session)->forPage($this->page('scoped')));
    }

    public function testNoWorkspacesConfiguredLeavesALiteralScopeAlone(): void
    {
        // A scope is not only a workspace. `'kind' => 'ad'` is a page saying
        // which rows of a shared table are its own, and it applies whether or
        // not anybody has workspaces.
        $scope = new RequestScope(
            new SessionWorkspace(new ArraySessionStore(), Workspaces::none()),
            Access::identityFor(Access::user()),
        );

        $this->assertSame(['kind' => 'ad'], $scope->forPage($this->page('literal')));
    }

    // --- harness ---

    /** @param list<string> $held */
    private function scope(array $held, ?ArraySessionStore $session = null): RequestScope
    {
        $workspaces = Workspaces::fromConfig([
            'cyklobazar' => ['vars' => ['tenant_id' => 1], 'pages' => '@all'],
            'motobazar' => ['vars' => ['tenant_id' => 2], 'pages' => '@all'],
        ], $this->pages());

        return new RequestScope(
            new SessionWorkspace($session ?? new ArraySessionStore(), $workspaces),
            Access::identityFor(Access::user(workspaces: $held)),
        );
    }

    private function page(string $name): \RockAdmin\Page\PageDefinition
    {
        return $this->pages()->get($name);
    }

    private function pages(): PageRepository
    {
        if (!isset($this->root)) {
            $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-scope-' . bin2hex(random_bytes(6));
            mkdir($this->root . '/pages', 0o777, true);

            $region = "'regions' => ['grid' => ['type' => 'list', 'columns' => ['id' => []]]]";

            $this->write('scoped', "['tenant_id' => '{{workspace.tenant_id}}']", $region);
            $this->write('shared', null, $region);
            $this->write(
                'mine',
                "['tenant_id' => '{{workspace.tenant_id}}', 'owner_id' => '{{user.id}}']",
                $region,
            );
            $this->write('literal', "['kind' => 'ad']", $region);
        }

        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    /** The scope lives under `entity`, which is where the page schema puts it. */
    private function write(string $name, ?string $scope, string $region): void
    {
        $entity = "'entity' => ['table' => 'ra_test_ads'"
            . ($scope === null ? '' : ", 'scope' => {$scope}")
            . ']';

        file_put_contents(
            $this->root . '/pages/' . $name . '.php',
            "<?php\n\nreturn ['title' => '{$name}', {$entity}, {$region}];\n",
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
