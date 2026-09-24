<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Page\PageRepository;
use RockAdmin\Tests\Support\DatabaseTestCase;

/**
 * `'fields' => '@all'` (spec 8.5) needs the table's real columns, which only
 * the database knows -- `Connection::columns()`, proved on both drivers by
 * `ConnectionTest`, is what it reads them with. This is the one place that
 * proves the loader actually wires the two together, over a real table, on
 * every configured driver.
 */
#[CoversClass(PageRepository::class)]
final class PageRepositoryFieldsTest extends DatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-page-fields-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
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

    private function writePage(string $name, string $body): void
    {
        file_put_contents($this->root . '/pages/' . $name . '.php', "<?php\n\nreturn {$body};\n");
    }

    private function repository(?Connection $connection): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
            25,
            $connection,
        );
    }

    #[DataProvider('connections')]
    public function testAllExpandsToEveryColumnOfTheTable(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'preview' => ['type' => 'preview', 'fields' => '@all'],
                ],
            ]
            PHP);

        $fields = $this->repository($connection)->get('ads')->region('preview')->fields;

        $this->assertSame(
            ['id', 'user_id', 'title', 'price', 'state', 'stats'],
            array_map(static fn ($f) => $f->key, $fields),
            'every column of the table, in the table\'s own order',
        );

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAllReusesAColumnTheGridAlreadyDescribes(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->createFixtures($connection);

        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['price' => ['type' => 'money']]],
                    'preview' => ['type' => 'preview', 'fields' => '@all'],
                ],
            ]
            PHP);

        $page = $this->repository($connection)->get('ads');
        $fields = $page->region('preview')->fields;

        $byKey = [];

        foreach ($fields as $field) {
            $byKey[$field->key] = $field;
        }

        $this->assertSame(
            \RockAdmin\Page\ColumnType::Money,
            $byKey['price']->type,
            "a column '@all' also finds on the grid keeps the grid's own definition, not a plain-text default",
        );
        $this->assertSame(\RockAdmin\Page\ColumnType::Text, $byKey['title']->type, 'a column with no grid definition falls back to plain text');

        $this->dropFixtures($connection);
    }

    public function testAllWithNoConnectionIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ra_test_ads'],
                'regions' => [
                    'preview' => ['type' => 'preview', 'fields' => '@all'],
                ],
            ]
            PHP);

        $this->expectException(\RockAdmin\Page\PageException::class);
        $this->expectExceptionMessageMatches('/@all/');

        $this->repository(null)->get('ads');
    }
}
