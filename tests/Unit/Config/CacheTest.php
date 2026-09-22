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

    public function testAClosureIsRefusedWithItsPath(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('extra');

        (new Cache($this->file))->write($this->config(static fn (): int => 1));
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

    public function testClearingAnAbsentCacheIsNotAnError(): void
    {
        (new Cache($this->file))->clear();

        $this->assertFileDoesNotExist($this->file);
    }
}
