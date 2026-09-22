# RockAdmin Milestone 2 — Configuration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Load a project's configuration directory into a validated, cacheable
object — with a machine-readable schema that makes an undocumented option
impossible, precise errors when a key is wrong, shared definitions that stop
the same column being described nine times, and placeholders that resolve
without ever becoming SQL text.

**Architecture:** Seven small classes under `src/Config/`, each one pass over
the configuration array. A schema describes what may appear; a validator
compares an array against it and reports paths, not just messages; a resolver
turns meaningful strings into typed objects; a definition expander inlines
shared fragments; a loader runs them in order; a cache writes the result as
plain PHP. No database, no HTTP — every test reads fixture files from disk.

**Tech Stack:** PHP 8.4, PHPUnit 11, PHPStan level max, PHP-CS-Fixer (PSR-12).

**Spec:** `docs/design/2026-09-21-rockadmin-design.md` — section 6 in full
(6.1 layout, 6.2 example page, 6.3 principles, 6.4 shared definitions,
6.5 enumerations, 6.6 caching, 6.7 naming), plus 11.4 on the local override.

## Global Constraints

Identical to milestone 1; they bind every task here too.

- **PHP 8.4 minimum.** Typed properties, constructor promotion, readonly, enums.
- **Composer runtime dependencies are `php`, `ext-pdo`, `ext-json`,
  `ext-mbstring` and nothing else.** `ComposerConstraintsTest` fails the build
  otherwise.
- **No framework references.** Nothing in `src/` may mention Laravel, Symfony
  or Illuminate, including in comments.
- **`declare(strict_types=1);` in every PHP file.**
- **PHPStan at level max.** Annotate array shapes. Never add `@phpstan-ignore`,
  a baseline entry, or an inline `@var` to silence an error — if PHPStan
  objects, first ask whether the declared type is honest. Milestone 1 lost two
  rounds to exactly that mistake.
- **PSR-12 via PHP-CS-Fixer**, short arrays, single quotes, ordered imports,
  trailing commas in multi-line calls. Run `composer run cs:fix` before
  committing.
- **Naming:** `PascalCase` classes, `camelCase` methods and variables,
  `snake_case` configuration keys.
- **English everywhere** — code, comments, commit messages, test names.
- **Namespaces:** `RockAdmin\` maps to `src/`, `RockAdmin\Tests\` to `tests/`.
- **Commit messages leave a blank line** between the subject and any trailers.
- **Verification before any completion claim:** run `composer run check` and
  read the output.

## Decisions this plan makes

These are not obvious from the spec and every task depends on them.

**Placeholders resolve in two phases.** `{{env.*}}` and `{{config.*}}` are
fixed for the lifetime of a deployment, so they resolve when configuration
loads and are baked into the cache. `{{workspace.*}}` and `{{user.*}}` differ
per request, so they survive into the cache as `Placeholder` objects and are
bound at request time. This is what lets a workspace variable become a bound
query parameter rather than text spliced into SQL.

**A missing environment variable resolves to `null`,** not to an exception.
The validator then reports it against the key that wanted a string — `mail.host
expects string, got null` names the actual problem, where a resolver exception
would only name the variable.

**`{{config.x}}` reads the raw, pre-resolution value.** A config placeholder
pointing at another placeholder is not supported; one pass, predictable result.

**`@enum:key` becomes an `EnumReference` object,** not an expanded option list.
Static enumerations could be inlined, but database-backed ones cannot, and one
shape for both is worth more than saving a lookup. The database source arrives
in milestone 3.

**Configuration holds data, never services.** No value may be a closure. This
is forced by the cache: `config.cache.php` is generated PHP, and a closure
cannot be written into it. Services the host replaces — the PDO connection, a
mail callback, the session store, the environment reader — are constructor
arguments at bootstrap. The spec was amended to match.

**Page, column, field and filter schemas are not in this milestone.** They
arrive with the regions that give them meaning, in milestones 6 to 8. What
ships here is the schema *mechanism* plus the schema for `rockadmin.php`,
which is the file this milestone actually loads.

**No CLI.** `rockadmin validate` and `rockadmin schema --json` are milestone
10, and will call this layer. The roadmap in milestone 1's plan listed them
here; they need a CLI runner that does not exist yet.

## File Structure

```
src/Config/
├── ValueType.php        enum: the types a schema key may declare
├── SchemaKey.php        one key: type, default, description, example, nesting
├── Schema.php           a set of keys, plus nearest() for "did you mean"
├── ValidationError.php  a path and a message
├── Validator.php        array + schema -> list of errors
├── Placeholder.php      a deferred {{workspace.x}} / {{user.x}}
├── EnumReference.php    a deferred @enum:key
├── Resolver.php         walks strings, producing values and typed objects
├── Definitions.php      expands ['use' => '@ns:key'] with deep merge
├── Enums.php            static enumerations and their options
├── EnumOption.php       one option: value, label, colour
├── Config.php           the loaded result, read by dotted path
├── Loader.php           reads the directory and runs the passes in order
├── Cache.php            writes and reads config.cache.php
├── ConfigException.php  every failure in this layer
└── RootSchema.php       the schema for rockadmin.php

tests/Unit/Config/       one test file per class with behaviour
tests/Fixtures/config/   real configuration directories the loader reads
```

---

### Task 1: Schema vocabulary

**Files:**
- Create: `src/Config/ValueType.php`, `src/Config/SchemaKey.php`, `src/Config/Schema.php`
- Test: `tests/Unit/Config/SchemaTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `enum RockAdmin\Config\ValueType: string` with cases `String`, `Int`,
    `Bool`, `Array`, `Mixed`
  - `SchemaKey` with readonly `ValueType $type`, `mixed $default`,
    `string $description`, `mixed $example`, `bool $required`,
    `?string $performance`, `?Schema $children`, `?Schema $each`
  - `Schema` with `__construct(array<string, SchemaKey> $keys)`,
    `key(string): ?SchemaKey`, `names(): list<string>`,
    `nearest(string): ?string`

`children` describes a nested array with known keys (`mail` has `driver`,
`host`, …). `each` describes a map with arbitrary keys whose values share a
shape (`enums` has one entry per enumeration). A key declares at most one.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

#[CoversClass(Schema::class)]
#[CoversClass(SchemaKey::class)]
#[CoversClass(ValueType::class)]
final class SchemaTest extends TestCase
{
    private function schema(): Schema
    {
        return new Schema([
            'per_page' => new SchemaKey(ValueType::Int, default: 50, description: 'Rows per page.'),
            'label' => new SchemaKey(ValueType::String, description: 'Shown in the header.'),
            'sortable' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Allows sorting by this column.',
                example: true,
                performance: 'Sorting an unindexed column causes a filesort.',
            ),
        ]);
    }

    public function testLooksUpAKey(): void
    {
        $key = $this->schema()->key('per_page');

        $this->assertNotNull($key);
        $this->assertSame(ValueType::Int, $key->type);
        $this->assertSame(50, $key->default);
        $this->assertFalse($key->required);
    }

    public function testAnUnknownKeyIsNull(): void
    {
        $this->assertNull($this->schema()->key('nope'));
    }

    public function testNamesAreListedInDeclarationOrder(): void
    {
        $this->assertSame(['per_page', 'label', 'sortable'], $this->schema()->names());
    }

    /** @return array<string, array{string, ?string}> */
    public static function suggestions(): array
    {
        return [
            'one letter wrong'   => ['lable', 'label'],
            'one letter missing' => ['sortabl', 'sortable'],
            'one letter extra'   => ['per_pages', 'per_page'],
            'exact match'        => ['label', 'label'],
            'nothing close'      => ['description', null],
            'far too short'      => ['x', null],
        ];
    }

    #[DataProvider('suggestions')]
    public function testNearestOnlySuggestsWhenItIsClose(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->schema()->nearest($input));
    }

    public function testAKeyCarriesItsDocumentation(): void
    {
        $key = $this->schema()->key('sortable');

        $this->assertNotNull($key);
        $this->assertSame('Allows sorting by this column.', $key->description);
        $this->assertTrue($key->example);
        $this->assertSame('Sorting an unindexed column causes a filesort.', $key->performance);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Config/SchemaTest.php`
Expected: FAIL — `Class "RockAdmin\Config\Schema" not found`.

- [ ] **Step 3: Write `ValueType`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * The types a configuration key may declare.
 *
 * Deliberately few: configuration is arrays of scalars, and a richer type
 * system here would be a schema language nobody asked for.
 */
enum ValueType: string
{
    case String = 'string';
    case Int = 'int';
    case Bool = 'bool';
    case Array = 'array';
    case Mixed = 'mixed';
}
```

- [ ] **Step 4: Write `SchemaKey`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use InvalidArgumentException;

/**
 * One configuration key, described well enough to generate its documentation.
 *
 * Every key the code reads has one of these, which is what makes an
 * undocumented option impossible: a key absent from the schema is rejected by
 * the validator, so a feature nobody wrote down cannot be used.
 */
final class SchemaKey
{
    public function __construct(
        public readonly ValueType $type,
        public readonly mixed $default = null,
        public readonly string $description = '',
        public readonly mixed $example = null,
        public readonly bool $required = false,
        public readonly ?string $performance = null,
        public readonly ?Schema $children = null,
        public readonly ?Schema $each = null,
    ) {
        if ($children !== null && $each !== null) {
            throw new InvalidArgumentException(
                'A schema key describes either named children or a map of '
                . 'uniform entries, never both.',
            );
        }
    }
}
```

- [ ] **Step 5: Write `Schema`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** A set of configuration keys. */
final class Schema
{
    /** @param array<string, SchemaKey> $keys */
    public function __construct(private readonly array $keys)
    {
    }

    public function key(string $name): ?SchemaKey
    {
        return $this->keys[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->keys);
    }

    /**
     * The closest declared key, when one is close enough to be a likely typo.
     *
     * "lable" should suggest "label"; "x" should suggest nothing, because a
     * confident wrong guess wastes more time than no guess. The threshold is
     * half the length of what was written.
     *
     * levenshtein() compares bytes, which is right here: configuration keys
     * are snake_case ASCII by convention (spec 6.7).
     */
    public function nearest(string $name): ?string
    {
        $best = null;
        $shortest = PHP_INT_MAX;

        foreach ($this->names() as $candidate) {
            $distance = levenshtein($name, $candidate);

            if ($distance < $shortest) {
                $shortest = $distance;
                $best = $candidate;
            }
        }

        return $shortest <= (int) ceil(strlen($name) / 2) ? $best : null;
    }
}
```

- [ ] **Step 6: Run the test, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/SchemaTest.php`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 7: Commit**

```bash
git add src/Config/ValueType.php src/Config/SchemaKey.php src/Config/Schema.php \
        tests/Unit/Config/SchemaTest.php
git commit -m "Add the configuration schema vocabulary"
```

---

### Task 2: Validator

**Files:**
- Create: `src/Config/ValidationError.php`, `src/Config/Validator.php`
- Test: `tests/Unit/Config/ValidatorTest.php`

**Interfaces:**
- Consumes: `Schema`, `SchemaKey`, `ValueType` from Task 1.
- Produces:
  - `ValidationError` with readonly `string $path`, `string $message`
  - `Validator::validate(array<string, mixed> $config, Schema $schema): list<ValidationError>`

Errors carry a dotted path (`mail.host`, `enums.ad_state.color`) because an
error without a location is a puzzle, not a message.

- [ ] **Step 1: Write the failing test**

```php
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
        ]);
    }

    /** @param list<ValidationError> $errors */
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Config/ValidatorTest.php`
Expected: FAIL — `Class "RockAdmin\Config\Validator" not found`.

- [ ] **Step 3: Write `ValidationError`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** One thing wrong with a configuration, and where. */
final class ValidationError
{
    public function __construct(
        public readonly string $path,
        public readonly string $message,
    ) {
    }
}
```

- [ ] **Step 4: Write `Validator`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * Compares a configuration array against a schema.
 *
 * Reports every problem rather than stopping at the first: a developer fixing
 * a configuration wants the whole list, not one error per run.
 */
final class Validator
{
    /**
     * @param  array<string, mixed>  $config
     * @return list<ValidationError>
     */
    public function validate(array $config, Schema $schema, string $prefix = ''): array
    {
        $errors = [];

        foreach ($config as $name => $value) {
            $path = $prefix === '' ? (string) $name : "{$prefix}.{$name}";
            $key = $schema->key((string) $name);

            if ($key === null) {
                $errors[] = new ValidationError($path, $this->unknownKeyMessage((string) $name, $schema));

                continue;
            }

            if (!$this->matches($value, $key->type)) {
                $errors[] = new ValidationError($path, \sprintf(
                    'Expected %s, got %s.',
                    $key->type->value,
                    get_debug_type($value),
                ));

                continue;
            }

            if (\is_array($value)) {
                $errors = [...$errors, ...$this->descend($value, $key, $path)];
            }
        }

        return [...$errors, ...$this->missingRequired($config, $schema, $prefix)];
    }

    /**
     * @param  array<array-key, mixed> $value
     * @return list<ValidationError>
     */
    private function descend(array $value, SchemaKey $key, string $path): array
    {
        if ($key->children !== null) {
            /** @var array<string, mixed> $value */
            return $this->validate($value, $key->children, $path);
        }

        if ($key->each === null) {
            return [];
        }

        $errors = [];

        foreach ($value as $entryName => $entry) {
            $entryPath = "{$path}.{$entryName}";

            if (!\is_array($entry)) {
                $errors[] = new ValidationError($entryPath, \sprintf(
                    'Expected array, got %s.',
                    get_debug_type($entry),
                ));

                continue;
            }

            /** @var array<string, mixed> $entry */
            $errors = [...$errors, ...$this->validate($entry, $key->each, $entryPath)];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<ValidationError>
     */
    private function missingRequired(array $config, Schema $schema, string $prefix): array
    {
        $errors = [];

        foreach ($schema->names() as $name) {
            $key = $schema->key($name);

            if ($key?->required === true && !\array_key_exists($name, $config)) {
                $path = $prefix === '' ? $name : "{$prefix}.{$name}";
                $errors[] = new ValidationError($path, 'Required key is missing.');
            }
        }

        return $errors;
    }

    private function unknownKeyMessage(string $name, Schema $schema): string
    {
        $nearest = $schema->nearest($name);

        return $nearest === null
            ? 'Unknown key.'
            : "Unknown key — did you mean '{$nearest}'?";
    }

    private function matches(mixed $value, ValueType $type): bool
    {
        return match ($type) {
            ValueType::String => \is_string($value),
            ValueType::Int => \is_int($value),
            ValueType::Bool => \is_bool($value),
            ValueType::Array => \is_array($value),
            ValueType::Mixed => true,
        };
    }
}
```

Note that `each` entries are validated for required keys too, because
`validate()` is what recurses — the map's own entry names are never checked
against a schema, since they are the project's own identifiers.

- [ ] **Step 5: Run the test, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/ValidatorTest.php`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 6: Commit**

```bash
git add src/Config/ValidationError.php src/Config/Validator.php \
        tests/Unit/Config/ValidatorTest.php
git commit -m "Add the configuration validator"
```

---

### Task 3: Placeholders, enum references and the resolver

**Files:**
- Create: `src/Config/ConfigException.php`, `src/Config/Placeholder.php`,
  `src/Config/EnumReference.php`, `src/Config/Resolver.php`
- Test: `tests/Unit/Config/ResolverTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `ConfigException extends RuntimeException`
  - `Placeholder` with readonly `string $namespace`, `string $name`,
    `__toString()`, and `static __set_state(array $data): self`
  - `EnumReference` with readonly `string $key`, `__toString()`, and
    `static __set_state(array $data): self`
  - `new Resolver(Closure $env, array<string, mixed> $raw)` and
    `resolve(array<string, mixed> $config): array<string, mixed>`,
    `warnings(): list<string>`

`__set_state` exists on both objects because `Cache` writes configuration with
`var_export()`, which emits exactly that call.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Placeholder;
use RockAdmin\Config\Resolver;

#[CoversClass(Resolver::class)]
#[CoversClass(Placeholder::class)]
#[CoversClass(EnumReference::class)]
final class ResolverTest extends TestCase
{
    /** @param array<string, string> $env */
    private function resolver(array $env = [], array $raw = []): Resolver
    {
        return new Resolver(
            static fn (string $name): ?string => $env[$name] ?? null,
            $raw,
        );
    }

    public function testAnEnvironmentPlaceholderResolvesToItsValue(): void
    {
        $resolved = $this->resolver(['MAIL_HOST' => 'smtp.example.com'])
            ->resolve(['mail' => ['host' => '{{env.MAIL_HOST}}']]);

        $this->assertSame(['mail' => ['host' => 'smtp.example.com']], $resolved);
    }

    public function testAMissingEnvironmentVariableResolvesToNull(): void
    {
        $resolved = $this->resolver()->resolve(['host' => '{{env.MAIL_HOST}}']);

        $this->assertSame(['host' => null], $resolved);
    }

    public function testAPlaceholderInsideTextIsInterpolated(): void
    {
        $resolved = $this->resolver(['CDN' => 'cdn.example.com'])
            ->resolve(['url' => 'https://{{env.CDN}}/assets']);

        $this->assertSame(['url' => 'https://cdn.example.com/assets'], $resolved);
    }

    public function testAConfigPlaceholderReadsTheRawValue(): void
    {
        $resolved = $this->resolver(raw: ['brand' => 'RockAdmin'])
            ->resolve(['title' => '{{config.brand}} admin']);

        $this->assertSame(['title' => 'RockAdmin admin'], $resolved);
    }

    public function testADeferredPlaceholderBecomesAnObject(): void
    {
        $resolved = $this->resolver()->resolve(['scope' => ['site_id' => '{{workspace.site_id}}']]);

        $placeholder = $resolved['scope']['site_id'];

        $this->assertInstanceOf(Placeholder::class, $placeholder);
        $this->assertSame('workspace', $placeholder->namespace);
        $this->assertSame('site_id', $placeholder->name);
        $this->assertSame('{{workspace.site_id}}', (string) $placeholder);
    }

    public function testADeferredPlaceholderInsideTextIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('{{workspace.site_id}}');

        $this->resolver()->resolve(['label' => 'site {{workspace.site_id}}']);
    }

    public function testAnUnknownNamespaceIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('magic');

        $this->resolver()->resolve(['x' => '{{magic.thing}}']);
    }

    public function testAnEnumReferenceBecomesAnObject(): void
    {
        $resolved = $this->resolver()->resolve(['options' => '@enum:ad_state']);

        $this->assertInstanceOf(EnumReference::class, $resolved['options']);
        $this->assertSame('ad_state', $resolved['options']->key);
    }

    public function testNonStringValuesArePassedThroughUnchanged(): void
    {
        $resolved = $this->resolver()->resolve(['per_page' => 50, 'debug' => true, 'none' => null]);

        $this->assertSame(['per_page' => 50, 'debug' => true, 'none' => null], $resolved);
    }

    public function testASecretLookingVariableIsWarnedAbout(): void
    {
        $resolver = $this->resolver(['MAIL_PASSWORD' => 'hunter2']);
        $resolver->resolve(['mail' => ['password' => '{{env.MAIL_PASSWORD}}']]);

        $this->assertCount(1, $resolver->warnings());
        $this->assertStringContainsString('MAIL_PASSWORD', $resolver->warnings()[0]);
    }

    public function testAnOrdinaryVariableIsNotWarnedAbout(): void
    {
        $resolver = $this->resolver(['MAIL_HOST' => 'smtp.example.com']);
        $resolver->resolve(['mail' => ['host' => '{{env.MAIL_HOST}}']]);

        $this->assertSame([], $resolver->warnings());
    }

    public function testObjectsSurviveVarExport(): void
    {
        $placeholder = new Placeholder('workspace', 'site_id');
        $enum = new EnumReference('ad_state');

        /** @var Placeholder $restoredPlaceholder */
        $restoredPlaceholder = eval('return ' . var_export($placeholder, true) . ';');
        /** @var EnumReference $restoredEnum */
        $restoredEnum = eval('return ' . var_export($enum, true) . ';');

        $this->assertEquals($placeholder, $restoredPlaceholder);
        $this->assertEquals($enum, $restoredEnum);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Config/ResolverTest.php`
Expected: FAIL — `Class "RockAdmin\Config\Resolver" not found`.

- [ ] **Step 3: Write `ConfigException`, `Placeholder` and `EnumReference`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use RuntimeException;

/** Anything wrong with a project's configuration. */
final class ConfigException extends RuntimeException
{
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A placeholder whose value is not known until a request is being served.
 *
 * `{{workspace.site_id}}` and `{{user.id}}` differ per request, so they cannot
 * be baked into the cached configuration. They survive it as objects and are
 * bound when the request is handled — which is also what keeps them out of SQL
 * text: a bound value can only ever be a value.
 */
final class Placeholder
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $name,
    ) {
    }

    public function __toString(): string
    {
        return "{{{$this->namespace}.{$this->name}}}";
    }

    /** @param array{namespace: string, name: string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['namespace'], $data['name']);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A reference to a shared enumeration, resolved when its options are needed.
 *
 * Static enumerations could be inlined here, but database-backed ones cannot,
 * and one shape for both is worth more than saving a lookup.
 */
final class EnumReference
{
    public function __construct(public readonly string $key)
    {
    }

    public function __toString(): string
    {
        return "@enum:{$this->key}";
    }

    /** @param array{key: string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['key']);
    }
}
```

- [ ] **Step 4: Write `Resolver`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use Closure;

/**
 * Turns the strings that carry meaning into values and objects.
 *
 * Two kinds of placeholder, separated by when they can be known:
 *
 * - `{{env.X}}` and `{{config.x}}` are fixed for a deployment, so they resolve
 *   here and are baked into the cache.
 * - `{{workspace.x}}` and `{{user.x}}` differ per request, so they become
 *   Placeholder objects that survive the cache and bind later.
 *
 * A missing environment variable resolves to null rather than raising: the
 * validator then reports it against the key that wanted a string, which names
 * the actual problem instead of naming the variable.
 */
final class Resolver
{
    private const DEFERRED = ['workspace', 'user'];

    private const IMMEDIATE = ['env', 'config'];

    private const SECRET_HINTS = ['KEY', 'SECRET', 'PASSWORD', 'TOKEN'];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param Closure(string): ?string $env reads the environment
     * @param array<string, mixed>     $raw the merged configuration, for {{config.x}}
     */
    public function __construct(
        private readonly Closure $env,
        private readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function resolve(array $config): array
    {
        $resolved = [];

        foreach ($config as $name => $value) {
            $resolved[$name] = $this->value($value);
        }

        return $resolved;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function value(mixed $value): mixed
    {
        if (\is_array($value)) {
            /** @var array<string, mixed> $value */
            return $this->resolve($value);
        }

        if (!\is_string($value)) {
            return $value;
        }

        if (preg_match('/^@enum:(\w+)$/', $value, $match) === 1) {
            return new EnumReference($match[1]);
        }

        return $this->string($value);
    }

    private function string(string $value): mixed
    {
        if (preg_match('/^\{\{(\w+)\.([\w.]+)}}$/', $value, $match) === 1) {
            return $this->single($match[1], $match[2], $value);
        }

        return preg_replace_callback(
            '/\{\{(\w+)\.([\w.]+)}}/',
            function (array $match): string {
                /** @var array{0: string, 1: string, 2: string} $match */
                if (\in_array($match[1], self::DEFERRED, true)) {
                    throw new ConfigException(
                        "{$match[0]} cannot appear inside a longer string. A "
                        . 'workspace or user placeholder becomes a bound value, '
                        . 'not text, so there is nothing to interpolate it into.',
                    );
                }

                return (string) $this->immediate($match[1], $match[2], $match[0]);
            },
            $value,
        ) ?? $value;
    }

    private function single(string $namespace, string $name, string $original): mixed
    {
        if (\in_array($namespace, self::DEFERRED, true)) {
            return new Placeholder($namespace, $name);
        }

        return $this->immediate($namespace, $name, $original);
    }

    private function immediate(string $namespace, string $name, string $original): mixed
    {
        if (!\in_array($namespace, self::IMMEDIATE, true)) {
            throw new ConfigException(
                "Unknown placeholder namespace '{$namespace}' in {$original}. "
                . 'Use env, config, workspace or user.',
            );
        }

        if ($namespace === 'config') {
            return $this->fromRaw($name);
        }

        $this->warnIfSecret($name);

        return ($this->env)($name);
    }

    /** Reads a dotted path out of the raw, pre-resolution configuration. */
    private function fromRaw(string $name): mixed
    {
        $value = $this->raw;

        foreach (explode('.', $name) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function warnIfSecret(string $name): void
    {
        foreach (self::SECRET_HINTS as $hint) {
            if (str_contains($name, $hint)) {
                $this->warnings[] = "{{env.{$name}}} looks like a secret. Configuration "
                    . 'values can end up rendered, so check this one is meant to be visible.';

                return;
            }
        }
    }
}
```

- [ ] **Step 5: Run the test, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/ResolverTest.php`, then `composer run check`.
Expected: PASS, then all green.

If PHPStan objects to `eval()` in the `var_export` test, replace it with
writing the exported string to a temporary file and `include`-ing it; do not
suppress the error.

- [ ] **Step 6: Commit**

```bash
git add src/Config/ConfigException.php src/Config/Placeholder.php \
        src/Config/EnumReference.php src/Config/Resolver.php \
        tests/Unit/Config/ResolverTest.php
git commit -m "Add placeholder and enum reference resolution"
```

---

### Task 4: Shared definitions

**Files:**
- Create: `src/Config/Definitions.php`
- Test: `tests/Unit/Config/DefinitionsTest.php`

**Interfaces:**
- Consumes: `ConfigException` from Task 3.
- Produces:
  - `new Definitions(array<string, array<string, array<string, mixed>>> $definitions)`
    — namespace, then key, then the definition
  - `expand(array<string, mixed> $config): array<string, mixed>`

Rules from spec 6.4: merging is deep, `null` removes an inherited key, a
definition may reference another, a cycle is reported with its chain, and an
unknown key names the nearest existing one.

- [ ] **Step 1: Write the failing test**

```php
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

        $this->assertSame(
            ['type' => 'date_range', 'op' => 'after'],
            $expanded['columns']['created_at']['filter'],
        );
    }

    public function testNullRemovesAnInheritedKey(): void
    {
        $expanded = $this->definitions()->expand([
            'columns' => ['created_at' => ['use' => '@column:created_at', 'filter' => null]],
        ]);

        $this->assertArrayNotHasKey('filter', $expanded['columns']['created_at']);
        $this->assertSame('datetime', $expanded['columns']['created_at']['type']);
    }

    public function testADefinitionMayReferenceAnotherDefinition(): void
    {
        $expanded = $this->definitions()->expand([
            'columns' => ['published_at' => ['use' => '@column:published_at']],
        ]);

        $this->assertSame('datetime', $expanded['columns']['published_at']['type']);
        $this->assertSame('Published', $expanded['columns']['published_at']['label']);
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
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Config/DefinitionsTest.php`
Expected: FAIL — `Class "RockAdmin\Config\Definitions" not found`.

- [ ] **Step 3: Write `Definitions`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * Inlines shared fragments referenced as ['use' => '@namespace:key'].
 *
 * Repeating a column definition on nine pages is how configuration rots: the
 * tenth gets it slightly wrong and nobody notices. Expansion happens once, at
 * load, and the result is what the cache stores — so a reference costs nothing
 * at runtime.
 */
final class Definitions
{
    /** @param array<string, array<string, array<string, mixed>>> $definitions namespace => key => definition */
    public function __construct(private readonly array $definitions)
    {
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function expand(array $config): array
    {
        /** @var array<string, mixed> $expanded */
        $expanded = $this->node($config, []);

        return $expanded;
    }

    /**
     * @param  array<string, mixed> $node
     * @param  list<string>         $chain references already being expanded
     * @return array<string, mixed>
     */
    private function node(array $node, array $chain): array
    {
        if (isset($node['use'])) {
            $node = $this->applyUse($node, $chain);
        }

        foreach ($node as $name => $value) {
            if (\is_array($value)) {
                /** @var array<string, mixed> $value */
                $node[$name] = $this->node($value, $chain);
            }
        }

        return $node;
    }

    /**
     * @param  array<string, mixed> $node
     * @param  list<string>         $chain
     * @return array<string, mixed>
     */
    private function applyUse(array $node, array $chain): array
    {
        $reference = $node['use'];
        unset($node['use']);

        if (!\is_string($reference) || preg_match('/^@(\w+):([\w.]+)$/', $reference, $match) !== 1) {
            $printed = \is_string($reference) ? $reference : get_debug_type($reference);

            throw new ConfigException(
                "'{$printed}' is not a definition reference. Write '@namespace:key', "
                . "for example '@column:id'.",
            );
        }

        [, $namespace, $key] = $match;
        $step = "{$namespace}:{$key}";

        if (\in_array($step, $chain, true)) {
            $printedChain = implode(' -> ', [...$chain, $step]);

            throw new ConfigException("Definition reference cycle: {$printedChain}.");
        }

        return $this->merge($this->node($this->lookup($namespace, $key), [...$chain, $step]), $node);
    }

    /** @return array<string, mixed> */
    private function lookup(string $namespace, string $key): array
    {
        if (!isset($this->definitions[$namespace])) {
            $known = implode(', ', array_keys($this->definitions));

            throw new ConfigException(
                "Unknown definition namespace '{$namespace}'. Known namespaces: {$known}.",
            );
        }

        if (!isset($this->definitions[$namespace][$key])) {
            $nearest = (new Schema(array_map(
                static fn (): SchemaKey => new SchemaKey(ValueType::Mixed),
                $this->definitions[$namespace],
            )))->nearest($key);

            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new ConfigException("Unknown definition '@{$namespace}:{$key}'.{$suffix}");
        }

        return $this->definitions[$namespace][$key];
    }

    /**
     * Overrides win; a null override removes the inherited key entirely.
     *
     * @param  array<string, mixed> $base
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $name => $value) {
            if ($value === null) {
                unset($base[$name]);

                continue;
            }

            if (\is_array($value) && \is_array($base[$name] ?? null)) {
                /** @var array<string, mixed> $existing */
                $existing = $base[$name];
                /** @var array<string, mixed> $value */
                $base[$name] = $this->merge($existing, $value);

                continue;
            }

            $base[$name] = $value;
        }

        return $base;
    }
}
```

- [ ] **Step 4: Run the test, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/DefinitionsTest.php`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 5: Commit**

```bash
git add src/Config/Definitions.php tests/Unit/Config/DefinitionsTest.php
git commit -m "Add shared definition expansion"
```

---

### Task 5: Enumerations

**Files:**
- Create: `src/Config/EnumOption.php`, `src/Config/Enums.php`
- Test: `tests/Unit/Config/EnumsTest.php`

**Interfaces:**
- Consumes: `EnumReference` and `ConfigException` from Task 3.
- Produces:
  - `EnumOption` with readonly `string $value`, `string $label`, `?string $color`
  - `Enums::fromConfig(array<string, mixed> $enums): self`
  - `options(EnumReference|string $enum): array<string, EnumOption>`
  - `has(string $key): bool`

Only static enumerations here. Database-backed ones (`'source' => [...]`)
arrive in milestone 3, when there is a connection to read them with; until
then `fromConfig()` rejects a `source` key with a message saying so, rather
than silently ignoring it.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Config/EnumsTest.php`
Expected: FAIL — `Class "RockAdmin\Config\Enums" not found`.

- [ ] **Step 3: Write `EnumOption`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** One option of a shared enumeration. */
final class EnumOption
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $color = null,
    ) {
    }

    /** @param array{value: string, label: string, color: string|null} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['value'], $data['label'], $data['color']);
    }
}
```

- [ ] **Step 4: Write `Enums`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * Shared enumerations, defined once and referenced as @enum:key.
 *
 * One definition feeds a filter, a form select and a badge colour, so the
 * three cannot drift apart.
 */
final class Enums
{
    /** @param array<string, array<string, EnumOption>> $enums */
    private function __construct(private readonly array $enums)
    {
    }

    /** @param array<string, mixed> $enums the contents of enums.php */
    public static function fromConfig(array $enums): self
    {
        $parsed = [];

        foreach ($enums as $key => $definition) {
            if (!\is_array($definition)) {
                throw new ConfigException(
                    "Enumeration '{$key}' must be an array of options, got "
                    . get_debug_type($definition) . '.',
                );
            }

            if (isset($definition['source'])) {
                throw new ConfigException(
                    "Enumeration '{$key}' reads its options from the database, which "
                    . 'arrives in milestone 3. Until then, list the options here.',
                );
            }

            $parsed[(string) $key] = self::parseOptions((string) $key, $definition);
        }

        return new self($parsed);
    }

    /** @param array{enums: array<string, array<string, EnumOption>>} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['enums']);
    }

    /**
     * @param  array<string, mixed>       $definition
     * @return array<string, EnumOption>
     */
    private static function parseOptions(string $enum, array $definition): array
    {
        $options = [];

        foreach ($definition as $value => $option) {
            $path = "{$enum}.{$value}";

            if (!\is_array($option) || !isset($option['label']) || !\is_string($option['label'])) {
                throw new ConfigException("Enumeration option '{$path}' needs a string label.");
            }

            $color = $option['color'] ?? null;

            if ($color !== null && !\is_string($color)) {
                throw new ConfigException("Enumeration option '{$path}' has a non-string color.");
            }

            $options[(string) $value] = new EnumOption((string) $value, $option['label'], $color);
        }

        return $options;
    }

    public function has(string $key): bool
    {
        return isset($this->enums[$key]);
    }

    /** @return array<string, EnumOption> */
    public function options(EnumReference|string $enum): array
    {
        $key = $enum instanceof EnumReference ? $enum->key : $enum;

        if (!isset($this->enums[$key])) {
            $nearest = (new Schema(array_map(
                static fn (): SchemaKey => new SchemaKey(ValueType::Mixed),
                $this->enums,
            )))->nearest($key);

            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new ConfigException("Unknown enumeration '@enum:{$key}'.{$suffix}");
        }

        return $this->enums[$key];
    }
}
```

Note that the parser is `parseOptions()` and the resolver is `options()`. PHP
has no method overloading, so they cannot share a name.

- [ ] **Step 5: Run the test, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/EnumsTest.php`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 6: Commit**

```bash
git add src/Config/EnumOption.php src/Config/Enums.php tests/Unit/Config/EnumsTest.php
git commit -m "Add shared enumerations"
```

---

### Task 6: Config, the root schema and the loader

**Files:**
- Create: `src/Config/Config.php`, `src/Config/RootSchema.php`, `src/Config/Loader.php`
- Create: `tests/Fixtures/config/valid/rockadmin.php`,
  `tests/Fixtures/config/valid/rockadmin.local.php`,
  `tests/Fixtures/config/valid/enums.php`,
  `tests/Fixtures/config/valid/defs.php`,
  `tests/Fixtures/config/invalid/rockadmin.php`
- Test: `tests/Unit/Config/ConfigTest.php`, `tests/Unit/Config/LoaderTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1 to 5.
- Produces:
  - `Config` with `get(string $path, mixed $default = null): mixed`,
    `has(string $path): bool`, `all(): array<string, mixed>`,
    `enums(): Enums`
  - `RootSchema::create(): Schema` — the schema for `rockadmin.php`
  - `new Loader(string $directory, Closure $env)` and `load(): Config`,
    `warnings(): list<string>`

The loader's order of passes is the design, and it is not interchangeable:

```
1. read rockadmin.php, merge rockadmin.local.php over it (deep)
2. read defs.php and enums.php if present
3. expand ['use' => '@ns:key'] references            (Definitions)
4. resolve strings into values and objects           (Resolver)
5. validate against the root schema                  (Validator)
6. build Enums from the resolved enums.php
```

Expansion precedes resolution so that a definition may itself contain a
placeholder. Validation is last so it judges what the admin will actually run
on, not what was typed.

- [ ] **Step 1: Write the fixtures**

`tests/Fixtures/config/valid/rockadmin.php`:

```php
<?php

declare(strict_types=1);

return [
    'url_mode' => 'path',
    'debug' => false,
    'brand' => 'RockAdmin',
    'paths' => [
        'logs' => 'storage/logs/rockadmin',
        'cache' => 'storage/cache/rockadmin',
    ],
    'mail' => [
        'driver' => 'log',
        'host' => '{{env.MAIL_HOST}}',
        'port' => 587,
    ],
    'assets' => ['css' => ['/css/admin.css'], 'js' => []],
];
```

`tests/Fixtures/config/valid/rockadmin.local.php`:

```php
<?php

declare(strict_types=1);

return [
    'debug' => true,
    'mail' => ['driver' => 'smtp'],
];
```

`tests/Fixtures/config/valid/enums.php`:

```php
<?php

declare(strict_types=1);

return [
    'ad_state' => [
        'active' => ['label' => 'Active', 'color' => 'success'],
        'draft' => ['label' => 'Draft'],
    ],
];
```

`tests/Fixtures/config/valid/defs.php`:

```php
<?php

declare(strict_types=1);

return [
    'column' => [
        'id' => ['type' => 'int', 'width' => '60px'],
    ],
];
```

`tests/Fixtures/config/invalid/rockadmin.php`:

```php
<?php

declare(strict_types=1);

return [
    'url_mod' => 'path',
    'debug' => 'yes',
];
```

- [ ] **Step 2: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Config;
use RockAdmin\Config\Enums;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    private function config(): Config
    {
        return new Config(
            ['debug' => true, 'paths' => ['logs' => 'storage/logs'], 'nothing' => null],
            Enums::fromConfig([]),
        );
    }

    public function testReadsATopLevelKey(): void
    {
        $this->assertTrue($this->config()->get('debug'));
    }

    public function testReadsANestedKeyByDottedPath(): void
    {
        $this->assertSame('storage/logs', $this->config()->get('paths.logs'));
    }

    public function testReturnsTheDefaultWhenAKeyIsAbsent(): void
    {
        $this->assertSame('fallback', $this->config()->get('paths.missing', 'fallback'));
        $this->assertNull($this->config()->get('nope.at.all'));
    }

    public function testHasDistinguishesAbsentFromNull(): void
    {
        $this->assertTrue($this->config()->has('nothing'));
        $this->assertFalse($this->config()->has('missing'));
    }

    public function testAllReturnsTheWholeArray(): void
    {
        $this->assertArrayHasKey('paths', $this->config()->all());
    }
}
```

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Loader;

#[CoversClass(Loader::class)]
final class LoaderTest extends TestCase
{
    private function directory(string $name): string
    {
        return \dirname(__DIR__, 2) . '/Fixtures/config/' . $name;
    }

    /** @param array<string, string> $env */
    private function loader(string $name, array $env = []): Loader
    {
        return new Loader(
            $this->directory($name),
            static fn (string $key): ?string => $env[$key] ?? null,
        );
    }

    public function testLoadsTheRootFile(): void
    {
        $config = $this->loader('valid')->load();

        $this->assertSame('path', $config->get('url_mode'));
        $this->assertSame('storage/logs/rockadmin', $config->get('paths.logs'));
    }

    public function testTheLocalOverrideWinsAndMergesDeeply(): void
    {
        $config = $this->loader('valid')->load();

        $this->assertTrue($config->get('debug'), 'the local file overrides debug');
        $this->assertSame('smtp', $config->get('mail.driver'), 'the local file overrides one key');
        $this->assertSame(587, $config->get('mail.port'), 'and leaves its siblings alone');
    }

    public function testEnvironmentPlaceholdersResolve(): void
    {
        $config = $this->loader('valid', ['MAIL_HOST' => 'smtp.example.com'])->load();

        $this->assertSame('smtp.example.com', $config->get('mail.host'));
    }

    public function testEnumerationsAreAvailable(): void
    {
        $config = $this->loader('valid')->load();

        $this->assertTrue($config->enums()->has('ad_state'));
        $this->assertSame('Active', $config->enums()->options('ad_state')['active']->label);
    }

    public function testAMissingDirectoryIsRefused(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('does-not-exist');

        (new Loader($this->directory('does-not-exist'), static fn (): ?string => null))->load();
    }

    public function testValidationErrorsAreReportedTogetherWithTheirPaths(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('url_mod');

        $this->loader('invalid')->load();
    }

    public function testEveryValidationErrorAppearsInTheMessage(): void
    {
        try {
            $this->loader('invalid')->load();
            $this->fail('Expected the invalid fixture to be refused.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString('url_mod', $e->getMessage());
            $this->assertStringContainsString('debug', $e->getMessage());
        }
    }

    public function testSecretLookingPlaceholdersAreCollectedAsWarnings(): void
    {
        $loader = $this->loader('valid');
        $loader->load();

        $this->assertSame([], $loader->warnings(), 'the valid fixture uses no secret-looking name');
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Config/ConfigTest.php tests/Unit/Config/LoaderTest.php`
Expected: FAIL — classes not found.

- [ ] **Step 4: Write `Config`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** A loaded, validated configuration. */
final class Config
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly array $data,
        private readonly Enums $enums,
    ) {
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $path): bool
    {
        $value = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    public function enums(): Enums
    {
        return $this->enums;
    }

    /** @param array{data: array<string, mixed>, enums: Enums} $state written by var_export() */
    public static function __set_state(array $state): self
    {
        return new self($state['data'], $state['enums']);
    }
}
```

`__set_state()` is what lets `Cache` write this object with `var_export()` and
get it back with a plain `include`. The key names are the property names,
because that is what `var_export()` emits.

- [ ] **Step 5: Write `RootSchema`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * The schema for rockadmin.php.
 *
 * Page, column, field and filter schemas arrive with the regions that give
 * them meaning, in later milestones. This is the file the loader reads today.
 */
final class RootSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'url_mode' => new SchemaKey(
                ValueType::String,
                default: 'path',
                description: "How links are built: 'path' needs a rewrite rule, 'query' does not.",
                example: 'path',
            ),
            'debug' => new SchemaKey(
                ValueType::Bool,
                default: false,
                description: 'Shows what went wrong instead of a neutral error page. Never on in production.',
                example: true,
            ),
            'brand' => new SchemaKey(
                ValueType::String,
                default: 'RockAdmin',
                description: 'Shown in the navbar.',
                example: 'Cyklobazar admin',
            ),
            'paths' => new SchemaKey(
                ValueType::Array,
                description: 'Writable directories, relative to the project root.',
                children: new Schema([
                    'logs' => new SchemaKey(
                        ValueType::String,
                        required: true,
                        description: 'Errors, mail and the audit trail. Must be outside the document root.',
                        example: 'storage/logs/rockadmin',
                    ),
                    'cache' => new SchemaKey(
                        ValueType::String,
                        description: 'Where the compiled configuration is written.',
                        example: 'storage/cache/rockadmin',
                        performance: 'Without it the configuration is read and validated on every request.',
                    ),
                ]),
            ),
            'mail' => new SchemaKey(
                ValueType::Array,
                description: 'How the admin sends a password reset.',
                children: new Schema([
                    'driver' => new SchemaKey(
                        ValueType::String,
                        default: 'log',
                        description: 'log, smtp, sendmail or callback.',
                        example: 'smtp',
                    ),
                    'host' => new SchemaKey(ValueType::String, description: 'SMTP host.', example: '{{env.MAIL_HOST}}'),
                    'port' => new SchemaKey(ValueType::Int, default: 587, description: 'SMTP port.', example: 587),
                ]),
            ),
            'assets' => new SchemaKey(
                ValueType::Array,
                description: 'Project CSS and JS, loaded after the SDK’s so they override it.',
                children: new Schema([
                    'css' => new SchemaKey(ValueType::Array, default: [], example: ['/css/admin.css']),
                    'js' => new SchemaKey(ValueType::Array, default: [], example: ['/js/admin.js']),
                ]),
            ),
        ]);
    }
}
```

- [ ] **Step 6: Write `Loader`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use Closure;

/**
 * Reads a configuration directory and runs the passes in order.
 *
 * The order is the design: references expand before placeholders resolve, so a
 * shared definition may contain one; validation runs last, so it judges what
 * the admin will actually run on rather than what was typed.
 */
final class Loader
{
    /** @var list<string> */
    private array $warnings = [];

    /** @param Closure(string): ?string $env */
    public function __construct(
        private readonly string $directory,
        private readonly Closure $env,
    ) {
    }

    public function load(): Config
    {
        if (!is_dir($this->directory)) {
            throw new ConfigException("Configuration directory not found: {$this->directory}");
        }

        $root = $this->merge($this->read('rockadmin.php'), $this->read('rockadmin.local.php'));

        $definitions = new Definitions($this->definitionsFrom($this->read('defs.php')));
        $expanded = $definitions->expand($root);

        $resolver = new Resolver($this->env, $root);
        $resolved = $resolver->resolve($expanded);
        $this->warnings = $resolver->warnings();

        $errors = (new Validator())->validate($resolved, RootSchema::create());

        if ($errors !== []) {
            throw new ConfigException($this->report($errors));
        }

        $enums = $definitions->expand($this->read('enums.php'));

        return new Config($resolved, Enums::fromConfig($resolver->resolve($enums)));
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array<string, mixed> */
    private function read(string $file): array
    {
        $path = $this->directory . '/' . $file;

        if (!is_file($path)) {
            return [];
        }

        /** @var mixed $contents */
        $contents = require $path;

        if (!\is_array($contents)) {
            throw new ConfigException(
                "{$path} must return an array, got " . get_debug_type($contents) . '.',
            );
        }

        /** @var array<string, mixed> $contents */
        return $contents;
    }

    /**
     * @param  array<string, mixed> $raw
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function definitionsFrom(array $raw): array
    {
        $definitions = [];

        foreach ($raw as $namespace => $entries) {
            if (!\is_array($entries)) {
                throw new ConfigException("Definition namespace '{$namespace}' must be an array.");
            }

            foreach ($entries as $key => $definition) {
                if (!\is_array($definition)) {
                    throw new ConfigException("Definition '@{$namespace}:{$key}' must be an array.");
                }

                /** @var array<string, mixed> $definition */
                $definitions[$namespace][(string) $key] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed> $base
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $name => $value) {
            if (\is_array($value) && \is_array($base[$name] ?? null)) {
                /** @var array<string, mixed> $existing */
                $existing = $base[$name];
                /** @var array<string, mixed> $value */
                $base[$name] = $this->merge($existing, $value);

                continue;
            }

            $base[$name] = $value;
        }

        return $base;
    }

    /** @param list<ValidationError> $errors */
    private function report(array $errors): string
    {
        $lines = array_map(
            static fn (ValidationError $e): string => "  {$e->path}: {$e->message}",
            $errors,
        );

        return \sprintf(
            "Configuration in %s is invalid:%s%s",
            $this->directory,
            PHP_EOL,
            implode(PHP_EOL, $lines),
        );
    }
}
```

- [ ] **Step 7: Run the tests, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 8: Commit**

```bash
git add src/Config/Config.php src/Config/RootSchema.php src/Config/Loader.php \
        tests/Unit/Config/ConfigTest.php tests/Unit/Config/LoaderTest.php \
        tests/Fixtures/config
git commit -m "Add the configuration loader and the root schema"
```

---

### Task 7: Cache

**Files:**
- Create: `src/Config/Cache.php`
- Test: `tests/Unit/Config/CacheTest.php`

**Interfaces:**
- Consumes: `Config`, `Enums`, `ConfigException` from earlier tasks.
- Produces:
  - `new Cache(string $file)`
  - `write(Config $config): void`
  - `read(): ?Config` — null when the file does not exist
  - `clear(): void`

The cache is generated PHP: one `include`, sitting in opcache, no parsing and
no validation on a production request. `Placeholder`, `EnumReference` and
`EnumOption` survive it through `__set_state()`. A closure cannot, which is
why configuration holds data and the host supplies services — `write()` says
so explicitly rather than producing a file that fails at include time.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Cache;
use RockAdmin\Config\Config;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Placeholder;

#[CoversClass(Cache::class)]
final class CacheTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rockadmin-cache-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    private function config(mixed $extra = null): Config
    {
        return new Config(
            [
                'debug' => true,
                'per_page' => 50,
                'paths' => ['logs' => 'storage/logs'],
                'scope' => ['site_id' => new Placeholder('workspace', 'site_id')],
                'options' => new EnumReference('ad_state'),
                'extra' => $extra,
            ],
            Enums::fromConfig(['ad_state' => ['active' => ['label' => 'Active', 'color' => 'success']]]),
        );
    }

    public function testReadingAnAbsentCacheReturnsNull(): void
    {
        $this->assertNull((new Cache($this->file))->read());
    }

    public function testAWrittenConfigurationComesBackIdentical(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $restored = $cache->read();

        $this->assertNotNull($restored);
        $this->assertSame(true, $restored->get('debug'));
        $this->assertSame(50, $restored->get('per_page'));
        $this->assertSame('storage/logs', $restored->get('paths.logs'));
    }

    public function testPlaceholdersSurviveTheRoundTrip(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $placeholder = $cache->read()?->get('scope.site_id');

        $this->assertInstanceOf(Placeholder::class, $placeholder);
        $this->assertSame('workspace', $placeholder->namespace);
        $this->assertSame('site_id', $placeholder->name);
    }

    public function testEnumerationsSurviveTheRoundTrip(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $restored = $cache->read();

        $this->assertNotNull($restored);
        $this->assertInstanceOf(EnumReference::class, $restored->get('options'));
        $this->assertSame('Active', $restored->enums()->options('ad_state')['active']->label);
    }

    public function testAClosureIsRefusedWithItsPath(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('extra');

        (new Cache($this->file))->write($this->config(static fn (): int => 1));
    }

    public function testClearRemovesTheFile(): void
    {
        $cache = new Cache($this->file);
        $cache->write($this->config());

        $this->assertFileExists($this->file);

        $cache->clear();

        $this->assertFileDoesNotExist($this->file);
        $this->assertNull($cache->read());
    }

    public function testClearingAnAbsentCacheIsNotAnError(): void
    {
        (new Cache($this->file))->clear();

        $this->assertFileDoesNotExist($this->file);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Config/CacheTest.php`
Expected: FAIL — `Class "RockAdmin\Config\Cache" not found`.

- [ ] **Step 3: Write `Cache`**

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * The compiled configuration: one include, sitting in opcache.
 *
 * In production this replaces reading a directory of files and validating
 * every key on every request. Placeholder, EnumReference and EnumOption
 * survive the round trip through __set_state(); a closure cannot, which is the
 * reason configuration holds data and the host supplies services.
 */
final class Cache
{
    public function __construct(private readonly string $file)
    {
    }

    public function read(): ?Config
    {
        if (!is_file($this->file)) {
            return null;
        }

        /** @var mixed $restored */
        $restored = require $this->file;

        if (!$restored instanceof Config) {
            throw new ConfigException(
                "{$this->file} is not a compiled configuration. Delete it and build it again.",
            );
        }

        return $restored;
    }

    public function write(Config $config): void
    {
        $this->assertExportable($config->all(), '');

        $source = \sprintf(
            "<?php\n\n// Generated by RockAdmin. Do not edit; rebuild it instead.\n\ndeclare(strict_types=1);\n\nreturn %s;\n",
            var_export($config, true),
        );

        $directory = \dirname($this->file);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new ConfigException("Cannot create the cache directory: {$directory}");
        }

        if (file_put_contents($this->file, $source, LOCK_EX) === false) {
            throw new ConfigException("Cannot write the configuration cache: {$this->file}");
        }
    }

    public function clear(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * Refuses anything var_export() cannot round-trip, naming where it is.
     *
     * Without this the failure would be an unreadable generated file at the
     * next request, rather than a message pointing at the offending key.
     *
     * @param array<array-key, mixed> $value
     */
    private function assertExportable(array $value, string $prefix): void
    {
        foreach ($value as $name => $entry) {
            $path = $prefix === '' ? (string) $name : "{$prefix}.{$name}";

            if (\is_array($entry)) {
                $this->assertExportable($entry, $path);

                continue;
            }

            if ($entry === null || \is_scalar($entry)) {
                continue;
            }

            if (\is_object($entry) && method_exists($entry, '__set_state')) {
                continue;
            }

            throw new ConfigException(\sprintf(
                "Configuration value at '%s' is a %s, which cannot be cached. "
                . 'Configuration holds data; services such as a connection, a '
                . 'mailer or a callback are given to the bootstrap instead.',
                $path,
                get_debug_type($entry),
            ));
        }
    }
}
```

`Config::__set_state()` and `Enums::__set_state()` were written in Tasks 6 and
5; they are what makes `var_export($config, true)` round-trip. `Enums` keeps
its private constructor — a static method on the class may call it.

- [ ] **Step 4: Run the test, then the full check**

Run: `vendor/bin/phpunit tests/Unit/Config/CacheTest.php`, then `composer run check`.
Expected: PASS, then all green.

- [ ] **Step 5: Update the changelog**

Under `## [Unreleased]` / `### Added`:

```markdown
- Configuration layer: schema with documentation for every key, a validator
  that reports paths and suggests near misses, shared definitions with deep
  merge, placeholders that resolve at load or bind per request, shared
  enumerations, the directory loader with its local override, and a compiled
  cache.
```

- [ ] **Step 6: Commit**

```bash
git add src/Config/Cache.php tests/Unit/Config/CacheTest.php CHANGELOG.md
git commit -m "Add the compiled configuration cache"
```

---

## Milestone acceptance

Verified by running each command and reading its output:

1. `composer run check` passes.
2. `composer show --tree` lists no runtime dependency beyond PHP extensions.
3. A configuration with three separate mistakes reports all three, each with
   its dotted path, and a misspelled key suggests the right one.
4. `Cache::write()` followed by `Cache::read()` returns a configuration equal
   to the original, including `Placeholder` and `EnumReference` values.
5. A configuration containing a closure is refused by name and path rather
   than producing a broken cache file.

## What this milestone deliberately leaves out

- Page, column, field and filter schemas — milestones 6 to 8, with the regions
  that give them meaning
- Database-backed enumerations — milestone 3
- Binding `{{workspace.*}}` and `{{user.*}}` to real values — milestone 5,
  where workspaces and identity exist
- Generating the reference documentation from the schema — milestone 12
- `rockadmin validate` and `rockadmin schema --json` — milestone 10, which
  builds the CLI runner; both will call this layer
- Wiring the loader into the kernel — milestone 10
