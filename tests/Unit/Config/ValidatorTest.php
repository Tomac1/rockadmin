<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValidationError;
use RockAdmin\Config\Validator;
use RockAdmin\Config\ValueType;

#[CoversClass(Validator::class)]
#[CoversClass(ValidationError::class)]
final class ValidatorTest extends TestCase
{
    private function schema(): Schema
    {
        return new Schema([
            'url_mode' => new SchemaKey(ValueType::String, default: 'path'),
            'debug' => new SchemaKey(ValueType::Bool, default: false),
            'paths' => new SchemaKey(ValueType::Array, children: new Schema([
                'logs' => new SchemaKey(ValueType::String, required: true),
                'cache' => new SchemaKey(ValueType::String),
            ])),
            'enums' => new SchemaKey(ValueType::Array, each: new Schema([
                'label' => new SchemaKey(ValueType::String, required: true),
                'color' => new SchemaKey(ValueType::String),
            ])),
            'smtp_host' => new SchemaKey(ValueType::String, nullable: true),
        ]);
    }

    /**
     * @param list<ValidationError> $errors
     * @return list<string>
     */
    private function messages(array $errors): array
    {
        return array_map(static fn (ValidationError $e): string => "{$e->path}: {$e->message}", $errors);
    }

    public function testAValidConfigurationProducesNoErrors(): void
    {
        $errors = (new Validator())->validate([
            'url_mode' => 'query',
            'debug' => true,
            'paths' => ['logs' => 'storage/logs', 'cache' => 'storage/cache'],
            'enums' => ['active' => ['label' => 'Active', 'color' => 'success']],
        ], $this->schema());

        $this->assertSame([], $this->messages($errors));
    }

    public function testAnUnknownKeySuggestsTheNearestOne(): void
    {
        $errors = (new Validator())->validate(['url_mod' => 'path'], $this->schema());

        $this->assertCount(1, $errors);
        $this->assertSame('url_mod', $errors[0]->path);
        $this->assertStringContainsString('Unknown key', $errors[0]->message);
        $this->assertStringContainsString('url_mode', $errors[0]->message);
    }

    public function testAnUnknownKeyWithNoCloseMatchListsNoSuggestion(): void
    {
        $errors = (new Validator())->validate(['x' => 1], $this->schema());

        $this->assertCount(1, $errors);
        $this->assertStringNotContainsString('did you mean', $errors[0]->message);
    }

    public function testAWrongTypeNamesBothTypes(): void
    {
        $errors = (new Validator())->validate(['debug' => 'yes'], $this->schema());

        $this->assertCount(1, $errors);
        $this->assertSame('debug', $errors[0]->path);
        $this->assertStringContainsString('bool', $errors[0]->message);
        $this->assertStringContainsString('string', $errors[0]->message);
    }

    public function testANullFromAMissingEnvironmentVariableIsATypeError(): void
    {
        $errors = (new Validator())->validate(['url_mode' => null], $this->schema());

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('null', $errors[0]->message);
    }

    public function testANullableKeyAcceptsNull(): void
    {
        // An optional setting backed by an environment variable that is not
        // set — an SMTP host while the mail driver is 'log', for instance.
        $this->assertSame([], (new Validator())->validate(['smtp_host' => null], $this->schema()));
    }

    public function testAMissingRequiredKeyIsReportedAtItsParentPath(): void
    {
        $errors = (new Validator())->validate(['paths' => ['cache' => 'x']], $this->schema());

        $this->assertSame(['paths.logs: Required key is missing.'], $this->messages($errors));
    }

    public function testErrorsInsideNamedChildrenCarryTheFullPath(): void
    {
        $errors = (new Validator())->validate(
            ['paths' => ['logs' => 'x', 'cahce' => 'y']],
            $this->schema(),
        );

        $this->assertCount(1, $errors);
        $this->assertSame('paths.cahce', $errors[0]->path);
        $this->assertStringContainsString('cache', $errors[0]->message);
    }

    public function testErrorsInsideAUniformMapCarryTheEntryKey(): void
    {
        $errors = (new Validator())->validate(
            ['enums' => ['active' => ['color' => 'success']]],
            $this->schema(),
        );

        $this->assertSame(['enums.active.label: Required key is missing.'], $this->messages($errors));
    }

    public function testEveryErrorIsReportedNotJustTheFirst(): void
    {
        $errors = (new Validator())->validate(
            ['debug' => 'yes', 'url_mode' => 42, 'nope' => true],
            $this->schema(),
        );

        $this->assertCount(3, $errors);
    }
}
