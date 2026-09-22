<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\RootSchema;
use RockAdmin\Config\ValueType;

#[CoversClass(RootSchema::class)]
final class RootSchemaTest extends TestCase
{
    public function testTemplatPathsKeyExists(): void
    {
        $schema = RootSchema::create();
        $key = $schema->key('template_paths');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Array, $key->type);
        $this->assertSame([], $key->default);
    }
}
