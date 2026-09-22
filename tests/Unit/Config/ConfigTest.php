<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Config;
use RockAdmin\Config\Enums;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    private function config(): Config
    {
        return new Config(
            ['debug' => true, 'paths' => ['logs' => 'storage/logs'], 'nothing' => null],
            Enums::fromConfig([]),
        );
    }

    public function testReadsATopLevelKey(): void
    {
        $this->assertTrue($this->config()->get('debug'));
    }

    public function testReadsANestedKeyByDottedPath(): void
    {
        $this->assertSame('storage/logs', $this->config()->get('paths.logs'));
    }

    public function testReturnsTheDefaultWhenAKeyIsAbsent(): void
    {
        $this->assertSame('fallback', $this->config()->get('paths.missing', 'fallback'));
        $this->assertNull($this->config()->get('nope.at.all'));
    }

    public function testHasDistinguishesAbsentFromNull(): void
    {
        $this->assertTrue($this->config()->has('nothing'));
        $this->assertFalse($this->config()->has('missing'));
    }

    public function testAllReturnsTheWholeArray(): void
    {
        $this->assertArrayHasKey('paths', $this->config()->all());
    }

    public function testGetReturnsTheDefaultWhenAPathRunsPastAScalar(): void
    {
        // 'paths.logs' is a string, so 'paths.logs.x' has nowhere to go. This is a
        // different branch from an absent key, which fails on its first segment.
        $this->assertSame('fallback', $this->config()->get('paths.logs.x', 'fallback'));
        $this->assertFalse($this->config()->has('paths.logs.x'));
    }
}
