<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\PageException;

#[CoversClass(FieldType::class)]
final class FieldTypeTest extends TestCase
{
    public function testEveryCaseSurvivesARoundTripThroughItsOwnValue(): void
    {
        // Both a value read out of configuration and a value written back
        // into a URL or a cache are the same string, because this is a
        // backed enum.
        foreach (FieldType::cases() as $type) {
            $this->assertSame($type, FieldType::parse($type->value));
        }
    }

    public function testAnUnknownTypeIsRefusedAndSuggestsTheNearest(): void
    {
        // 'txet' is a typo somebody will make.
        $this->expectException(PageException::class);
        $this->expectExceptionMessage('text');

        FieldType::parse('txet');
    }

    public function testTheRefusalListsEveryTypeWhenNothingIsClose(): void
    {
        try {
            FieldType::parse('quantum');
            $this->fail('An unknown type should throw.');
        } catch (PageException $e) {
            foreach (FieldType::cases() as $type) {
                $this->assertStringContainsString($type->value, $e->getMessage());
            }
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function everyType(): array
    {
        return [
            'text' => ['text', false],
            'textarea' => ['textarea', false],
            'number' => ['number', false],
            'select' => ['select', true],
            'multiselect' => ['multiselect', true],
            'checkbox' => ['checkbox', false],
            'radio' => ['radio', true],
            'date' => ['date', false],
            'datetime' => ['datetime', false],
            'hidden' => ['hidden', false],
            'password' => ['password', false],
        ];
    }

    #[DataProvider('everyType')]
    public function testTakesOptionsIsTrueForExactlySelectMultiselectAndRadio(string $type, bool $takesOptions): void
    {
        $this->assertSame($takesOptions, FieldType::parse($type)->takesOptions());
    }

    /** @return array<string, array{string, bool}> */
    public static function everyTypeForMultiple(): array
    {
        return [
            'text' => ['text', false],
            'textarea' => ['textarea', false],
            'number' => ['number', false],
            'select' => ['select', false],
            'multiselect' => ['multiselect', true],
            'checkbox' => ['checkbox', false],
            'radio' => ['radio', false],
            'date' => ['date', false],
            'datetime' => ['datetime', false],
            'hidden' => ['hidden', false],
            'password' => ['password', false],
        ];
    }

    #[DataProvider('everyTypeForMultiple')]
    public function testIsMultipleIsTrueForExactlyMultiselect(string $type, bool $isMultiple): void
    {
        $this->assertSame($isMultiple, FieldType::parse($type)->isMultiple());
    }

    public function testEveryCaseNamesATemplateUnderTheFieldDirectory(): void
    {
        // Asserted over cases() rather than one literal per type, so that a
        // twelfth field type cannot be added without also giving it a
        // template — a test that spelled out eleven pairs would not notice.
        foreach (FieldType::cases() as $type) {
            $this->assertSame('field/' . $type->value, $type->template());
        }
    }
}
