<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Cache;
use RockAdmin\Config\Config;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Placeholder;

#[CoversClass(Cache::class)]
final class CacheTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rockadmin-cache-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        foreach (glob($this->file . '.*.tmp') ?: [] as $leftover) {
            unlink($leftover);
        }

        foreach ($this->directories as $directory) {
            foreach (array_merge(glob($directory . '/pages/*') ?: [], glob($directory . '/*.php') ?: []) as $file) {
                unlink($file);
            }

            @rmdir($directory . '/pages');
            @rmdir($directory);
        }
    }

    private function config(mixed $extra = null): Config
    {
        return new Config(
            [
                'debug' => true,
                'per_page' => 50,
                'paths' => ['logs' => 'storage/logs'],
                'scope' => ['site_id' => new Placeholder('workspace', 'site_id')],
                'options' => new EnumReference('ad_state'),
                'extra' => $extra,
            ],
            Enums::fromConfig(['ad_state' => ['active' => ['label' => 'Active', 'color' => 'success']]]),
        );
    }

    public function testReadingAnAbsentCacheReturnsNull(): void
    {
        $this->assertNull((new Cache($this->file))->read());
    }

    public function testACacheWrittenByAnOlderRockAdminIsRebuiltRatherThanReadPartially(): void
    {
        // A cache file predates a constructor argument -- `roles`, here, but
        // it will be something else next time. var_export() wrote no such key,
        // so __set_state() restores an object that is valid and quietly
        // incomplete: a configuration reporting no roles at all, which once
        // the gate is in place means nobody may do anything and nothing says
        // why. Read as absent so the caller rebuilds it.
        file_put_contents($this->file, <<<'PHP'
            <?php

            declare(strict_types=1);

            return \RockAdmin\Config\Config::__set_state([
                'data' => ['per_page' => 50],
                'enums' => \RockAdmin\Config\Enums::__set_state(['enums' => [], 'sources' => []]),
            ]);
            PHP);

        $this->assertNull((new Cache($this->file))->read(), 'a cache without a version is from before versions existed');
    }

    public function testACacheWrittenByThisVersionIsRead(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $restored = $cache->read();

        $this->assertNotNull($restored);
        $this->assertTrue($restored->isCurrent());
    }

    public function testAWrittenConfigurationComesBackIdentical(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $restored = $cache->read();

        $this->assertNotNull($restored);
        $this->assertSame(true, $restored->get('debug'));
        $this->assertSame(50, $restored->get('per_page'));
        $this->assertSame('storage/logs', $restored->get('paths.logs'));
    }

    public function testPlaceholdersSurviveTheRoundTrip(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $placeholder = $cache->read()?->get('scope.site_id');

        $this->assertInstanceOf(Placeholder::class, $placeholder);
        $this->assertSame('workspace', $placeholder->namespace);
        $this->assertSame('site_id', $placeholder->name);
    }

    public function testEnumerationsSurviveTheRoundTrip(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $restored = $cache->read();

        $this->assertNotNull($restored);
        $this->assertInstanceOf(EnumReference::class, $restored->get('options'));
        $this->assertSame('Active', $restored->enums()->options('ad_state')['active']->label);
    }

    public function testADatabaseBackedEnumerationSurvivesTheRoundTrip(): void
    {
        $cache = new Cache($this->file);
        $cache->write(new Config(
            ['debug' => true],
            Enums::fromConfig([
                'categories' => [
                    'source' => ['table' => 'categories', 'value' => 'id', 'label' => 'name'],
                ],
            ]),
        ));

        $restored = $cache->read();

        $this->assertNotNull($restored);
        $this->assertTrue(
            $restored->enums()->has('categories'),
            'a cached configuration must not lose its database-backed enumerations',
        );
    }

    public function testAClosureIsRefusedWithItsPath(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('extra');

        (new Cache($this->file))->write($this->config(static fn (): int => 1));
    }

    public function testWritingOverAnExistingCacheReplacesIt(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());
        $cache->write(new Config(['debug' => false], Enums::fromConfig([])));

        $restored = $cache->read();

        $this->assertNotNull($restored);
        $this->assertFalse($restored->get('debug'));
        $this->assertNull($restored->get('per_page'), 'the first write must be gone, not merged');
        $this->assertCount(0, glob($this->file . '.*.tmp') ?: []);
    }

    public function testReadingAFileThatIsNotACompiledConfigurationIsRefused(): void
    {
        file_put_contents($this->file, "<?php\n\nreturn ['not' => 'a config'];\n");

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($this->file);

        (new Cache($this->file))->read();
    }

    public function testClearRemovesTheFile(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $this->assertFileExists($this->file);

        $cache->clear();

        $this->assertFileDoesNotExist($this->file);
        $this->assertNull($cache->read());
    }

    // --- going stale on its own ---

    /** @return array{Cache, string} the cache, and a configuration directory it watches */
    private function watched(): array
    {
        $directory = str_replace('\\', '/', sys_get_temp_dir()) . '/rockadmin-watched-' . bin2hex(random_bytes(6));
        mkdir($directory . '/pages', 0o777, true);
        file_put_contents($directory . '/rockadmin.php', "<?php return ['debug' => true];\n");
        file_put_contents($directory . '/pages/ads.php', "<?php return [];\n");
        $this->directories[] = $directory;

        $cache = new Cache($this->file);
        $cache->write($this->config(), Cache::directoriesOf($directory, $this->config()));

        return [$cache, $directory];
    }

    /** @var list<string> */
    private array $directories = [];

    public function testACacheOverUnchangedFilesIsRead(): void
    {
        [$cache] = $this->watched();

        $this->assertNotNull($cache->read());
    }

    public function testEditingAConfigurationFileMakesTheCacheStale(): void
    {
        [$cache, $directory] = $this->watched();

        file_put_contents($directory . '/rockadmin.php', "<?php return ['debug' => false, 'per_page' => 10];\n");
        touch($directory . '/rockadmin.php', time() + 10);
        clearstatcache();

        $this->assertNull($cache->read(), 'An edited rockadmin.php was served from the old cache.');
    }

    public function testAddingAPageMakesTheCacheStale(): void
    {
        [$cache, $directory] = $this->watched();

        file_put_contents($directory . '/pages/users.php', "<?php return [];\n");
        clearstatcache();

        $this->assertNull($cache->read(), 'A new page file was not noticed.');
    }

    public function testDeletingAPageMakesTheCacheStale(): void
    {
        [$cache, $directory] = $this->watched();

        unlink($directory . '/pages/ads.php');
        clearstatcache();

        $this->assertNull($cache->read());
    }

    public function testACacheFromBeforeTheFingerprintIsRebuilt(): void
    {
        file_put_contents($this->file, '<?php return ' . var_export($this->config(), true) . ';');

        $this->assertNull((new Cache($this->file))->read());
    }

    public function testClearingAnAbsentCacheIsNotAnError(): void
    {
        (new Cache($this->file))->clear();

        $this->assertFileDoesNotExist($this->file);
    }
}
