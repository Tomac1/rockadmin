<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Definitions;

#[CoversClass(Definitions::class)]
final class DefinitionsTest extends TestCase
{
    private function definitions(): Definitions
    {
        return new Definitions([
            'column' => [
                'id' => ['type' => 'int', 'width' => '60px', 'sortable' => true],
                'created_at' => [
                    'type' => 'datetime',
                    'sortable' => true,
                    'filter' => ['type' => 'date_range', 'op' => 'between'],
                ],
                'published_at' => ['use' => '@column:created_at', 'label' => 'Published'],
            ],
            'field' => [
                'email' => ['type' => 'text', 'validate' => ['email']],
            ],
        ]);
    }

    public function testAReferenceIsReplacedByItsDefinition(): void
    {
        $expanded = $this->definitions()->expand(['columns' => ['id' => ['use' => '@column:id']]]);

        $this->assertSame(
            ['columns' => ['id' => ['type' => 'int', 'width' => '60px', 'sortable' => true]]],
            $expanded,
        );
    }

    public function testOverridesWinOverTheDefinition(): void
    {
        $expanded = $this->definitions()->expand([
            'columns' => ['id' => ['use' => '@column:id', 'width' => '80px']],
        ]);

        $this->assertSame(
            ['columns' => ['id' => ['type' => 'int', 'width' => '80px', 'sortable' => true]]],
            $expanded,
        );
    }

    public function testMergingIsDeep(): void
    {
        $expanded = $this->definitions()->expand([
            'columns' => ['created_at' => ['use' => '@column:created_at', 'filter' => ['op' => 'after']]],
        ]);

        /** @var array<string, array<string, array<string, mixed>>> $columns */
        $columns = $expanded['columns'];
        /** @var array<string, mixed> $createdAt */
        $createdAt = $columns['created_at'];
        /** @var array<string, mixed> $filter */
        $filter = $createdAt['filter'];

        $this->assertSame(
            ['type' => 'date_range', 'op' => 'after'],
            $filter,
        );
    }

    public function testNullRemovesAnInheritedKey(): void
    {
        $expanded = $this->definitions()->expand([
            'columns' => ['created_at' => ['use' => '@column:created_at', 'filter' => null]],
        ]);

        /** @var array<string, array<string, array<string, mixed>>> $columns */
        $columns = $expanded['columns'];
        /** @var array<string, mixed> $createdAt */
        $createdAt = $columns['created_at'];

        $this->assertArrayNotHasKey('filter', $createdAt);
        $this->assertSame('datetime', $createdAt['type']);
    }

    public function testADefinitionMayReferenceAnotherDefinition(): void
    {
        $expanded = $this->definitions()->expand([
            'columns' => ['published_at' => ['use' => '@column:published_at']],
        ]);

        /** @var array<string, array<string, array<string, mixed>>> $columns */
        $columns = $expanded['columns'];
        /** @var array<string, mixed> $publishedAt */
        $publishedAt = $columns['published_at'];

        $this->assertSame('datetime', $publishedAt['type']);
        $this->assertSame('Published', $publishedAt['label']);
    }

    public function testAnUnknownKeySuggestsTheNearestOne(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('created_at');

        $this->definitions()->expand(['columns' => ['x' => ['use' => '@column:created_ad']]]);
    }

    public function testAnUnknownNamespaceIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('widget');

        $this->definitions()->expand(['columns' => ['x' => ['use' => '@widget:thing']]]);
    }

    public function testACycleIsReportedWithItsChain(): void
    {
        $definitions = new Definitions([
            'column' => [
                'a' => ['use' => '@column:b'],
                'b' => ['use' => '@column:a'],
            ],
        ]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('column:a -> column:b -> column:a');

        $definitions->expand(['columns' => ['x' => ['use' => '@column:a']]]);
    }

    public function testAMalformedReferenceIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('column.id');

        $this->definitions()->expand(['columns' => ['x' => ['use' => 'column.id']]]);
    }

    public function testConfigurationWithoutReferencesIsUntouched(): void
    {
        $config = ['columns' => ['title' => ['type' => 'text'], 'n' => 5], 'per_page' => 50];

        $this->assertSame($config, $this->definitions()->expand($config));
    }

    public function testListValuesPassThroughUnmangled(): void
    {
        $expanded = $this->definitions()->expand([
            'fields' => ['email' => ['use' => '@field:email', 'placeholder' => 'you@example.com']],
            'assets' => ['css' => ['/a.css', '/b.css']],
        ]);

        /** @var array<string, mixed> $fields */
        $fields = $expanded['fields'];
        /** @var array<string, mixed> $email */
        $email = $fields['email'];

        /** @var array<string, mixed> $assets */
        $assets = $expanded['assets'];
        /** @var array<int, string> $css */
        $css = $assets['css'];

        $this->assertSame(['email'], $email['validate']);
        $this->assertSame(['/a.css', '/b.css'], $css);
    }

    public function testANonStringUseValueIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('array');

        $this->definitions()->expand(['columns' => ['x' => ['use' => ['@column:id']]]]);
    }
}
