<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Enums;
use RockAdmin\Config\PageReference;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;

/**
 * Where a @page: reference is checked against the catalogue.
 *
 * `Resolver` parses the syntax because that is where the syntax lives; it
 * cannot say whether the page exists, because pages are the layer that
 * consumes its output. So the refusal is here, where the directory is.
 */
#[CoversClass(PageRepository::class)]
final class PageReferenceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-refs-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/pages/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->root . '/pages')) {
            rmdir($this->root . '/pages');
        }

        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    private function repository(): PageRepository
    {
        file_put_contents($this->root . '/pages/users.php', "<?php\n\nreturn [];\n");
        file_put_contents($this->root . '/pages/ad_groups.php', "<?php\n\nreturn [];\n");

        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    public function testAReferenceToAPageThatExistsPasses(): void
    {
        $this->repository()->assertPageExists(new PageReference('users', '{id}/edit'), "page 'ads': action 'edit'");

        $this->addToAssertionCount(1);
    }

    public function testAReferenceToAPageThatDoesNotExistIsRefusedNamingTheNearest(): void
    {
        try {
            $this->repository()->assertPageExists(new PageReference('usrs'), "page 'ads': action 'author'");
            $this->fail('Expected a reference to a missing page to be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString("page 'ads': action 'author'", $e->getMessage());
            $this->assertStringContainsString('@page:usrs', $e->getMessage());
            $this->assertStringContainsString("Did you mean 'users'?", $e->getMessage());
        }
    }

    public function testAReferenceNowhereNearAPageIsRefusedWithoutAGuess(): void
    {
        // Schema::nearestOf() returns nothing when no candidate is close, and a
        // confident wrong guess wastes more time than no guess.
        try {
            $this->repository()->assertPageExists(new PageReference('x'), "page 'ads'");
            $this->fail('Expected a reference to a missing page to be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString('@page:x', $e->getMessage());
            $this->assertStringNotContainsString('Did you mean', $e->getMessage());
        }
    }
}
