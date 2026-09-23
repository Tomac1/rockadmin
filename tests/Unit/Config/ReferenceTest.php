<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Reference;
use RockAdmin\Config\RootSchema;
use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

#[CoversClass(Reference::class)]
final class ReferenceTest extends TestCase
{
    public function testAKeyBecomesARowCarryingEverythingSomeoneNeedsToWriteIt(): void
    {
        $schema = new Schema([
            'per_page' => new SchemaKey(
                ValueType::Int,
                default: 25,
                description: 'Rows in one page of a grid.',
                example: 50,
            ),
        ]);

        $markdown = Reference::markdown($schema, 'Reference');

        $this->assertStringContainsString('| `per_page` | int | `25` | `50` | Rows in one page of a grid. |', $markdown);
    }

    public function testTheFileSaysItIsGenerated(): void
    {
        // Someone will eventually open this file to fix a typo. The first
        // thing they should read is that the typo lives in the schema.
        $markdown = Reference::markdown(new Schema([]), 'Reference');

        $this->assertStringContainsString('Generated from the schema', $markdown);
        $this->assertStringContainsString('Do not edit by hand', $markdown);
    }

    public function testARequiredKeySaysSo(): void
    {
        $schema = new Schema([
            'logs' => new SchemaKey(ValueType::String, required: true, description: 'Where errors go.'),
        ]);

        $this->assertStringContainsString('**Required.**', Reference::markdown($schema, 'Reference'));
    }

    public function testANullableKeySaysSoInItsType(): void
    {
        $schema = new Schema([
            'host' => new SchemaKey(ValueType::String, nullable: true, description: 'SMTP host.'),
        ]);

        $this->assertStringContainsString('| string or null |', Reference::markdown($schema, 'Reference'));
    }

    public function testAPerformanceNoteIsCarriedThrough(): void
    {
        // The reason a key exists is often "it is slow without this", and that
        // is exactly what someone deciding whether to set it wants to read.
        $schema = new Schema([
            'cache' => new SchemaKey(
                ValueType::String,
                description: 'Compiled configuration.',
                performance: 'Without it the configuration is validated on every request.',
            ),
        ]);

        $markdown = Reference::markdown($schema, 'Reference');

        $this->assertStringContainsString('*Performance:* Without it the configuration', $markdown);
    }

    public function testABlockBecomesItsOwnHeadingRatherThanARow(): void
    {
        $schema = new Schema([
            'mail' => new SchemaKey(
                ValueType::Array,
                description: 'How the admin sends mail.',
                children: new Schema([
                    'driver' => new SchemaKey(ValueType::String, default: 'log', description: 'Which driver.'),
                ]),
            ),
        ]);

        $markdown = Reference::markdown($schema, 'Reference');

        $this->assertStringContainsString('## `mail`', $markdown);
        $this->assertStringContainsString('How the admin sends mail.', $markdown);
        $this->assertStringContainsString('| `driver` | string | `log` |', $markdown);

        // A row for the block itself would say "array" and nothing more.
        $this->assertStringNotContainsString('| `mail` |', $markdown);
    }

    public function testANestedBlockIsAddressedByItsFullPath(): void
    {
        $schema = new Schema([
            'a' => new SchemaKey(
                ValueType::Array,
                children: new Schema([
                    'b' => new SchemaKey(
                        ValueType::Array,
                        children: new Schema([
                            'c' => new SchemaKey(ValueType::String, description: 'Deep.'),
                        ]),
                    ),
                ]),
            ),
        ]);

        $this->assertStringContainsString('## `a.b`', Reference::markdown($schema, 'Reference'));
    }

    public function testARepeatedEntrySchemaIsDocumentedOnce(): void
    {
        $schema = new Schema([
            'columns' => new SchemaKey(
                ValueType::Array,
                description: 'The grid columns.',
                each: new Schema([
                    'type' => new SchemaKey(ValueType::String, description: 'Which column type.'),
                ]),
            ),
        ]);

        $markdown = Reference::markdown($schema, 'Reference');

        $this->assertStringContainsString('## `columns.*`', $markdown);
        $this->assertStringContainsString('Every entry of `columns`.', $markdown);
        $this->assertStringContainsString('| `type` | string |', $markdown);
    }

    public function testAnArrayDefaultIsWrittenAsSomeoneWouldType(): void
    {
        $schema = new Schema([
            'css' => new SchemaKey(ValueType::Array, default: [], example: ['/css/admin.css'], description: 'Stylesheets.'),
        ]);

        $markdown = Reference::markdown($schema, 'Reference');

        $this->assertStringContainsString('`[]`', $markdown);
        $this->assertStringContainsString("`['/css/admin.css']`", $markdown);
    }

    public function testAPipeInADescriptionCannotEndTheTableCell(): void
    {
        // Not untrusted input, but one pipe in one description would silently
        // shift every column of that row and nobody would look here for it.
        $schema = new Schema([
            'mode' => new SchemaKey(ValueType::String, description: 'Either a | or a b.'),
        ]);

        $markdown = Reference::markdown($schema, 'Reference');

        $this->assertStringContainsString('Either a \\| or a b.', $markdown);
    }

    public function testEveryDeclaredRootKeyReachesTheReference(): void
    {
        // The rule this whole class exists for: an undeclared key means an
        // undocumented feature. Checked against the real schema, so adding a
        // key that the renderer cannot describe fails here.
        $schema = RootSchema::create();
        $markdown = Reference::markdown($schema, 'Configuration reference');

        foreach ($schema->names() as $name) {
            $this->assertStringContainsString("`{$name}`", $markdown, "The key {$name} is not in the reference.");
        }
    }

    public function testANestedExampleIsWrittenOutRatherThanElided(): void
    {
        // An enum option carrying a badge colour is a map of maps, and it is
        // the one shape nobody can guess. Rendering it as […] documented that
        // something goes there and nothing about what.
        $schema = new Schema([
            'options' => new SchemaKey(
                ValueType::Array,
                description: 'The options.',
                example: ['active' => ['label' => 'Active', 'color' => 'success']],
            ),
        ]);

        $markdown = Reference::markdown($schema, 'Reference');

        $this->assertStringContainsString(
            "`['active' => ['label' => 'Active', 'color' => 'success']]`",
            $markdown,
        );
    }

    public function testNestingIsElidedOnceItHasStoppedBeingAnExample(): void
    {
        $schema = new Schema([
            'deep' => new SchemaKey(
                ValueType::Array,
                description: 'Too deep.',
                example: ['a' => ['b' => ['c' => ['d' => 1]]]],
            ),
        ]);

        $this->assertStringContainsString('[…]', Reference::markdown($schema, 'Reference'));
    }
}
