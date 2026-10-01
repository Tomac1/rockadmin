<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Permission;
use RockAdmin\Auth\Roles;
use RockAdmin\Auth\RoleSchema;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Enums;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Page\PageRepository;

/**
 * Roles are configuration and assignment is data (spec 9.2); this covers the
 * configuration half.
 *
 * The permission catalogue is generated from the pages, so a page with no form
 * region brings only its `view` — the same fact that decides whether its grid
 * draws an edit link, read from the page definition rather than assumed.
 */
#[CoversClass(Roles::class)]
#[CoversClass(Permission::class)]
#[CoversClass(RoleSchema::class)]
final class RolesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-roles-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);

        // `ads` and `users` are writable; `reports` has a list region and no
        // form, so it generates `reports.view` and nothing else.
        $this->writePage('ads', true);
        $this->writePage('users', true);
        $this->writePage('reports', false);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
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

    /** @return list<string> */
    private function catalogue(): array
    {
        return Permission::catalogue(new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        ));
    }

    /** @param array<string, mixed> $roles */
    private function roles(array $roles): Roles
    {
        return Roles::fromConfig($roles, $this->catalogue());
    }

    /** @param list<string> $permissions */
    private function role(array $permissions): Roles
    {
        return $this->roles(['r' => ['permissions' => $permissions]]);
    }

    public function testAStarAllowsEverything(): void
    {
        $roles = $this->role(['*']);

        $this->assertSame($this->catalogue(), $roles->permissions('r'));
        $this->assertTrue($roles->allows(['r'], 'ads.delete'));
        $this->assertTrue($roles->allows(['r'], 'reports.view'));
    }

    public function testAPageStarAllowsThatPageAndNoOther(): void
    {
        $roles = $this->role(['ads.*']);

        $this->assertSame(
            ['ads.create', 'ads.delete', 'ads.update', 'ads.view'],
            $roles->permissions('r'),
        );
        $this->assertFalse($roles->allows(['r'], 'users.view'));
    }

    public function testAStarViewAllowsEveryViewAndNoWrite(): void
    {
        $roles = $this->role(['*.view']);

        $this->assertSame(['ads.view', 'reports.view', 'users.view'], $roles->permissions('r'));
        $this->assertFalse($roles->allows(['r'], 'ads.update'));
    }

    public function testASubtractionRemovesOnePermissionFromAWidening(): void
    {
        $roles = $this->role(['ads.*', '!ads.delete']);

        $this->assertSame(['ads.create', 'ads.update', 'ads.view'], $roles->permissions('r'));
        $this->assertFalse($roles->allows(['r'], 'ads.delete'));
    }

    /**
     * The property that matters: every subtraction applies after every
     * widening, so the meaning of a role never depends on the order somebody
     * happened to type it in. An implementation that folds the list in
     * sequence passes every other test in this file and fails this one.
     */
    public function testTheOrderOfWideningAndSubtractionDoesNotMatter(): void
    {
        $written = ['ads.*', 'users.view', '!ads.delete', 'reports.*'];
        $shuffled = ['!ads.delete', 'reports.*', 'ads.*', 'users.view'];

        $this->assertSame(
            $this->role($written)->permissions('r'),
            $this->role($shuffled)->permissions('r'),
        );

        // And not trivially equal because both resolved to nothing.
        $this->assertSame(
            ['ads.create', 'ads.update', 'ads.view', 'reports.view', 'users.view'],
            $this->role($written)->permissions('r'),
        );
    }

    public function testAnUnknownPermissionIsDenied(): void
    {
        $roles = $this->role(['*']);

        // The gate fails closed: a permission nobody generated is denied even
        // to a role holding every permission that exists.
        $this->assertFalse($roles->allows(['r'], 'ads.publish'));
        $this->assertFalse($roles->allows(['r'], 'invoices.view'));
        $this->assertFalse($roles->allows(['nosuchrole'], 'ads.view'));
    }

    public function testAPageWithNoFormGeneratesNoWritePermissions(): void
    {
        $catalogue = $this->catalogue();

        $this->assertContains('reports.view', $catalogue);
        $this->assertNotContains('reports.create', $catalogue);
        $this->assertNotContains('reports.update', $catalogue);
        $this->assertNotContains('reports.delete', $catalogue);
        $this->assertContains('ads.delete', $catalogue);

        $this->assertSame(['reports.view'], $this->role(['reports.*'])->permissions('r'));
    }

    public function testARoleNamingAPageThatDoesNotExistIsRefused(): void
    {
        try {
            $this->role(['add.view']);
            $this->fail('Expected a ConfigException.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("'add.view'", $e->getMessage());
            $this->assertStringContainsString('no page', $e->getMessage());
            $this->assertStringContainsString("'ads'", $e->getMessage());
        }
    }

    public function testAPermissionWithAnUnknownActionIsRefused(): void
    {
        try {
            $this->role(['ads.publish']);
            $this->fail('Expected a ConfigException.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("'ads.publish'", $e->getMessage());

            foreach (Permission::ACTIONS as $action) {
                $this->assertStringContainsString("'{$action}'", $e->getMessage());
            }
        }
    }

    public function testARoleWithNoPermissionsKeyIsRefused(): void
    {
        try {
            $this->roles(['editor' => ['label' => 'Editor']]);
            $this->fail('Expected a ConfigException.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("'editor'", $e->getMessage());
            $this->assertStringContainsString('permissions', $e->getMessage());
        }
    }

    public function testSubtractingEverythingIsRefused(): void
    {
        try {
            $this->role(['*', '!*']);
            $this->fail('Expected a ConfigException.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString("'!*'", $e->getMessage());
        }
    }

    public function testALabelDefaultsToTheRolesOwnKey(): void
    {
        $roles = $this->roles([
            'editor' => ['label' => 'Content editor', 'permissions' => ['ads.*']],
            'viewer' => ['permissions' => ['*.view']],
        ]);

        $this->assertSame(['editor', 'viewer'], $roles->names());
        $this->assertSame('Content editor', $roles->label('editor'));
        $this->assertSame('viewer', $roles->label('viewer'));
        $this->assertTrue($roles->has('editor'));
        $this->assertFalse($roles->has('superadmin'));
    }

    public function testSeveralRolesUnionTheirPermissions(): void
    {
        $roles = $this->roles([
            'viewer' => ['permissions' => ['*.view']],
            'deleter' => ['permissions' => ['ads.delete']],
        ]);

        $this->assertTrue($roles->allows(['viewer', 'deleter'], 'ads.delete'));
        $this->assertTrue($roles->allows(['viewer', 'deleter'], 'users.view'));
        $this->assertFalse($roles->allows(['viewer'], 'ads.delete'));
        $this->assertFalse($roles->allows([], 'ads.view'));
    }

    public function testEveryDeclaredKeyCarriesADescriptionAndAnExample(): void
    {
        // Rule 5: an undeclared or undescribed key is an undocumented feature.
        // ReferenceIsCurrentTest asserts the same over every generated file;
        // asserting it here fails the schema before the reference does.
        $schema = RoleSchema::create();

        $this->assertNotSame([], $schema->names());

        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            $this->assertInstanceOf(SchemaKey::class, $key);
            $this->assertNotSame('', $key->description, "The key {$name} has no description.");
            $this->assertNotNull($key->example, "The key {$name} has no example.");
        }
    }
}
