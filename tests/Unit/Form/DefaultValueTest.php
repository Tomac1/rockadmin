<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Placeholder;
use RockAdmin\Form\DefaultValue;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\PageException;

#[CoversClass(DefaultValue::class)]
final class DefaultValueTest extends TestCase
{
    public function testALiteralIsItself(): void
    {
        $field = $this->field(FieldType::Text, 'draft');

        $this->assertSame('draft', DefaultValue::for($field));
    }

    public function testNowForADateFieldIsADateWithoutATime(): void
    {
        $field = $this->field(FieldType::Date, '@now');
        $now = new \DateTimeImmutable('2026-03-14 09:26:53');

        $this->assertSame('2026-03-14', DefaultValue::for($field, $now));
    }

    public function testNowForADatetimeFieldCarriesTheTime(): void
    {
        $field = $this->field(FieldType::Datetime, '@now');
        $now = new \DateTimeImmutable('2026-03-14 09:26:53');

        $this->assertSame('2026-03-14 09:26:53', DefaultValue::for($field, $now));
    }

    public function testNowForAFieldOfNeitherDateNorDatetimeTypeCarriesTheFullTimestamp(): void
    {
        // A text field defaulting to '@now' has no type of its own to shape
        // the value by, so it gets the same full timestamp a datetime field
        // would — the most complete answer, rather than guessing that a
        // bare date was meant.
        $field = $this->field(FieldType::Text, '@now');
        $now = new \DateTimeImmutable('2026-03-14 09:26:53');

        $this->assertSame('2026-03-14 09:26:53', DefaultValue::for($field, $now));
    }

    public function testNowIsTakenFromTheInjectedClockSoATestCanPinIt(): void
    {
        $field = $this->field(FieldType::Datetime, '@now');
        $now = new \DateTimeImmutable('2000-01-01 00:00:00');

        $this->assertSame('2000-01-01 00:00:00', DefaultValue::for($field, $now));
    }

    public function testUuidLooksLikeAVersionFourUuid(): void
    {
        $field = $this->field(FieldType::Text, '@uuid');

        $uuid = DefaultValue::for($field);

        $this->assertIsString($uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        );
    }

    public function testTwoUuidsDiffer(): void
    {
        $field = $this->field(FieldType::Text, '@uuid');

        $this->assertNotSame(DefaultValue::for($field), DefaultValue::for($field));
    }

    public function testAPlaceholderSurvivesUntouched(): void
    {
        $placeholder = new Placeholder('workspace', 'site_id');
        $field = $this->field(FieldType::Text, $placeholder);

        $this->assertSame($placeholder, DefaultValue::for($field));
    }

    public function testAFieldWithNoDefaultHasNone(): void
    {
        $field = $this->field(FieldType::Text, null);

        $this->assertNull(DefaultValue::for($field));
    }

    public function testAnUnknownTokenIsRefusedNamingBoth(): void
    {
        $field = $this->field(FieldType::Text, '@nwo');

        $this->expectException(PageException::class);
        $this->expectExceptionMessage('@now');
        $this->expectExceptionMessageMatches('/@uuid/');

        DefaultValue::for($field);
    }

    public function testAStringThatMerelyStartsWithAnAtIsStillRefused(): void
    {
        $field = $this->field(FieldType::Text, '@handle');

        $this->expectException(PageException::class);

        DefaultValue::for($field);
    }

    public function testIsTokenIsTrueForNowAndUuid(): void
    {
        $this->assertTrue(DefaultValue::isToken('@now'));
        $this->assertTrue(DefaultValue::isToken('@uuid'));
    }

    public function testIsTokenIsFalseForALiteralOrAPlaceholderOrNull(): void
    {
        $this->assertFalse(DefaultValue::isToken('draft'));
        $this->assertFalse(DefaultValue::isToken(new Placeholder('workspace', 'site_id')));
        $this->assertFalse(DefaultValue::isToken(null));
    }

    private function field(FieldType $type, mixed $default): FieldDefinition
    {
        return new FieldDefinition(
            key: 'field',
            label: 'Field',
            type: $type,
            default: $default,
            required: false,
            readonly: false,
            hidden: false,
            help: '',
            placeholder: '',
        );
    }
}
