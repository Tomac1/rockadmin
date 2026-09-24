<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;
use RockAdmin\Page\FieldSchema;
use RockAdmin\Page\FormSchema;
use RockAdmin\Page\PageSchema;
use RockAdmin\Page\RegionType;

#[CoversClass(FormSchema::class)]
#[CoversClass(FieldSchema::class)]
final class FormSchemaTest extends TestCase
{
    public function testRegionTypeParsesForm(): void
    {
        $this->assertSame(RegionType::Form, RegionType::parse('form'));
    }

    public function testARegionDeclaresAFormKeyUsingFormSchema(): void
    {
        $schema = PageSchema::create();
        $regionsKey = $schema->key('regions');

        $this->assertNotNull($regionsKey);
        $this->assertNotNull($regionsKey->each);

        $formKey = $regionsKey->each->key('form');
        $this->assertNotNull($formKey);
        $this->assertSame(ValueType::Array, $formKey->type);
        $this->assertNotNull($formKey->children);
        $this->assertNotNull($formKey->children->key('fields'));
        $this->assertNotNull($formKey->children->key('copy'));
    }

    public function testFormSchemaDeclaresFieldsAndCopy(): void
    {
        $schema = FormSchema::create();

        $this->assertNotNull($schema->key('fields'));
        $this->assertNotNull($schema->key('copy'));
    }

    public function testFieldsUsesEachSoEveryFieldIsValidatedAlike(): void
    {
        $schema = FormSchema::create();
        $key = $schema->key('fields');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Array, $key->type);
        $this->assertNotNull($key->each);
    }

    public function testCopyResetIsAList(): void
    {
        $schema = FormSchema::create();
        $key = $schema->key('copy');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Array, $key->type);
        $this->assertNotNull($key->children);

        $resetKey = $key->children->key('reset');
        $this->assertNotNull($resetKey);
        $this->assertSame(ValueType::Array, $resetKey->type);
    }

    public function testFieldSchemaDeclaresEveryKeyFromTheBrief(): void
    {
        $schema = FieldSchema::create();

        foreach ([
            'type', 'label', 'default', 'required', 'readonly', 'hidden',
            'help', 'placeholder', 'options', 'min', 'max', 'step', 'rows', 'pattern',
        ] as $name) {
            $this->assertNotNull($schema->key($name), "FieldSchema has no key '{$name}'.");
        }
    }

    public function testTypeIsAStringWithDefaultText(): void
    {
        $schema = FieldSchema::create();
        $key = $schema->key('type');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::String, $key->type);
        $this->assertSame('text', $key->default);
    }

    public function testRequiredReadonlyAndHiddenDefaultToFalse(): void
    {
        $schema = FieldSchema::create();

        foreach (['required', 'readonly', 'hidden'] as $name) {
            $key = $schema->key($name);
            $this->assertNotNull($key);
            $this->assertSame(ValueType::Bool, $key->type);
            $this->assertFalse($key->default);
        }
    }

    public function testEveryKeyInFormSchemaCarriesADescriptionAndExample(): void
    {
        $schema = FormSchema::create();

        foreach ($this->everyKey($schema) as $path => $key) {
            $this->assertNotSame('', $key->description, "The form schema key {$path} has no description.");

            if ($key->children === null && $key->each === null && $key->type->value !== 'bool') {
                $this->assertNotNull($key->example, "The form schema key {$path} has no example.");
            }
        }
    }

    public function testEveryKeyInFieldSchemaCarriesADescriptionAndExample(): void
    {
        $schema = FieldSchema::create();

        foreach ($this->everyKey($schema) as $path => $key) {
            $this->assertNotSame('', $key->description, "The field schema key {$path} has no description.");

            if ($key->children === null && $key->each === null && $key->type->value !== 'bool') {
                $this->assertNotNull($key->example, "The field schema key {$path} has no example.");
            }
        }
    }

    /** @return array<string, SchemaKey> */
    private function everyKey(Schema $schema, string $prefix = ''): array
    {
        $found = [];

        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            if ($key === null) {
                continue;
            }

            $path = $prefix === '' ? $name : $prefix . '.' . $name;
            $found[$path] = $key;

            foreach ([$key->children, $key->each] as $nested) {
                if ($nested !== null) {
                    $found = [...$found, ...$this->everyKey($nested, $path)];
                }
            }
        }

        return $found;
    }
}
