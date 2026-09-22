<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\DbException;
use RockAdmin\Db\SourcePath;

#[CoversClass(SourcePath::class)]
final class SourcePathTest extends TestCase
{
    public function testAPlainColumnBelongsToTheEntitysOwnTable(): void
    {
        $path = SourcePath::parse('title');

        $this->assertNull($path->relation);
        $this->assertSame('title', $path->column);
        $this->assertSame([], $path->json);
        $this->assertSame([], $path->joins());
    }

    public function testASingleRelation(): void
    {
        $path = SourcePath::parse('user.name');

        $this->assertSame('user', $path->relation);
        $this->assertSame('name', $path->column);
        $this->assertSame(['user'], $path->joins());
    }

    public function testAChainedRelationNeedsEveryPrefixJoinedInOrder(): void
    {
        $path = SourcePath::parse('user.company.name');

        $this->assertSame('user.company', $path->relation);
        $this->assertSame('name', $path->column);
        $this->assertSame(['user', 'user.company'], $path->joins());
    }

    public function testJsonTraversalStaysInsideTheRow(): void
    {
        $path = SourcePath::parse('stats->daily->views');

        $this->assertNull($path->relation);
        $this->assertSame('stats', $path->column);
        $this->assertSame(['daily', 'views'], $path->json);
        $this->assertSame([], $path->joins(), 'JSON adds no join');
    }

    public function testARelationAndJsonTogether(): void
    {
        $path = SourcePath::parse('user.profile->locale');

        $this->assertSame('user', $path->relation);
        $this->assertSame('profile', $path->column);
        $this->assertSame(['locale'], $path->json);
        $this->assertSame(['user'], $path->joins());
    }

    /** @return array<string, array{string, string}> */
    public static function malformed(): array
    {
        return [
            'empty' => ['', 'empty segment'],
            'trailing dot' => ['user.', 'empty segment'],
            'leading dot' => ['.name', 'empty segment'],
            'double dot' => ['user..name', 'empty segment'],
            'trailing arrow' => ['stats->', 'empty JSON key'],
            'empty json key' => ['stats->->views', 'empty JSON key'],
            'arrow before dot' => ['stats->daily.views', 'cannot appear after an arrow'],
        ];
    }

    #[DataProvider('malformed')]
    public function testAMalformedPathIsRefused(string $source, string $expected): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage($expected);

        SourcePath::parse($source);
    }
}
