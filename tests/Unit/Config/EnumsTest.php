<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\EnumOption;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Placeholder;

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

    public function testADatabaseBackedEnumerationIsAccepted(): void
    {
        $enums = Enums::fromConfig([
            'categories' => [
                'source' => ['table' => 'categories', 'value' => 'id', 'label' => 'name'],
            ],
        ]);

        $this->assertTrue($enums->has('categories'));
    }

    public function testADatabaseBackedEnumerationNeedsAConnectionToResolve(): void
    {
        $enums = Enums::fromConfig([
            'categories' => [
                'source' => ['table' => 'categories', 'value' => 'id', 'label' => 'name'],
            ],
        ]);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('categories');

        $enums->options('categories');
    }

    public function testASourceMissingItsTableIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('table');

        Enums::fromConfig(['categories' => ['source' => ['value' => 'id', 'label' => 'name']]]);
    }

    public function testASourceThatIsNotAnArrayIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('categories');

        Enums::fromConfig(['categories' => ['source' => 'categories']]);
    }

    public function testANonStringLabelIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('ad_state.active');

        Enums::fromConfig(['ad_state' => ['active' => ['label' => 123]]]);
    }

    public function testANonStringColourIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('ad_state.active');

        Enums::fromConfig(['ad_state' => ['active' => ['label' => 'Active', 'color' => 123]]]);
    }

    public function testAnOptionKeyedSourceIsNotMistakenForADatabaseBackedEnumeration(): void
    {
        $enums = Enums::fromConfig(['origin' => [
            'source' => ['label' => 'Source'],
            'manual' => ['label' => 'Manual'],
        ]]);

        $this->assertSame('Source', $enums->options('origin')['source']->label);
    }

    public function testAPlaceholderLabelIsRefusedAndSaysWhy(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('placeholder');

        Enums::fromConfig(['ad_state' => [
            'active' => ['label' => new Placeholder('workspace', 'name')],
        ]]);
    }
}
