<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\EnumOption;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Enums;

#[CoversClass(Enums::class)]
#[CoversClass(EnumOption::class)]
final class EnumsTest extends TestCase
{
    private function enums(): Enums
    {
        return Enums::fromConfig([
            'ad_state' => [
                'active' => ['label' => 'Active', 'color' => 'success'],
                'draft' => ['label' => 'Draft'],
            ],
        ]);
    }

    public function testOptionsAreReturnedInDeclarationOrder(): void
    {
        $options = $this->enums()->options('ad_state');

        $this->assertSame(['active', 'draft'], array_keys($options));
    }

    public function testAnOptionCarriesItsValueLabelAndColour(): void
    {
        $option = $this->enums()->options('ad_state')['active'];

        $this->assertInstanceOf(EnumOption::class, $option);
        $this->assertSame('active', $option->value);
        $this->assertSame('Active', $option->label);
        $this->assertSame('success', $option->color);
    }

    public function testAColourIsOptional(): void
    {
        $this->assertNull($this->enums()->options('ad_state')['draft']->color);
    }

    public function testAReferenceResolvesTheSameWayAsAKey(): void
    {
        $this->assertEquals(
            $this->enums()->options('ad_state'),
            $this->enums()->options(new EnumReference('ad_state')),
        );
    }

    public function testHas(): void
    {
        $this->assertTrue($this->enums()->has('ad_state'));
        $this->assertFalse($this->enums()->has('nope'));
    }

    public function testAnUnknownEnumerationSuggestsTheNearestOne(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('ad_state');

        $this->enums()->options('ad_stat');
    }

    public function testAMissingLabelIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('label');

        Enums::fromConfig(['ad_state' => ['active' => ['color' => 'success']]]);
    }

    public function testADatabaseBackedEnumerationIsRefusedForNow(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('milestone 3');

        Enums::fromConfig(['categories' => ['source' => ['table' => 'categories']]]);
    }
}
