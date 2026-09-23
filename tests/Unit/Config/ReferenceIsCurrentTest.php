<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Reference;
use RockAdmin\Config\RootSchema;

/**
 * Rule 5 of this project, made real: every configuration key exists in the
 * schema, with type, default, description and example, and the reference is
 * generated from it — so an undeclared key means an undocumented feature and
 * fails CI.
 *
 * The committed file is what a reader and an agent writing configuration will
 * actually open, and it is only worth anything if it cannot drift. This
 * compares it against what the schema would produce today.
 */
#[CoversNothing]
final class ReferenceIsCurrentTest extends TestCase
{
    private const PATH = '/docs/reference/configuration.md';

    public function testTheCommittedReferenceMatchesTheSchema(): void
    {
        $file = \dirname(__DIR__, 3) . self::PATH;

        $this->assertFileExists($file, 'Run `composer run docs:reference`.');

        $committed = file_get_contents($file) ?: '';
        $generated = $this->generate();

        $this->assertSame(
            str_replace("\r\n", "\n", $generated),
            str_replace("\r\n", "\n", $committed),
            'The configuration reference is out of date. Run `composer run docs:reference` and commit the result.',
        );
    }

    public function testEveryDocumentedKeyCarriesADescriptionAndAnExample(): void
    {
        // A row with an empty description documents that a key exists and
        // nothing else, which is the shape of documentation that makes an
        // agent guess. Types with a sensible single value — bool — need no
        // example, so only the description is required of them.
        $schema = RootSchema::create();

        foreach ($this->everyKey($schema) as $path => $key) {
            $this->assertNotSame('', $key->description, "The key {$path} has no description.");

            if ($key->children === null && $key->each === null && $key->type->value !== 'bool') {
                $this->assertNotNull($key->example, "The key {$path} has no example.");
            }
        }
    }

    /**
     * @return array<string, \RockAdmin\Config\SchemaKey>
     */
    private function everyKey(\RockAdmin\Config\Schema $schema, string $prefix = ''): array
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

    private function generate(): string
    {
        // The same call bin/generate-reference.php makes. Duplicating the
        // intro text would mean this test passes while the committed file is
        // wrong, so the script is the one place it is written.
        $script = file_get_contents(\dirname(__DIR__, 3) . '/bin/generate-reference.php') ?: '';

        $this->assertStringContainsString('Reference::markdown(RootSchema::create()', $script);

        if (preg_match("/\\\$intro = <<<'TEXT'\n(.*?)\nTEXT;/s", $script, $matches) !== 1) {
            $this->fail('Could not read the intro text out of bin/generate-reference.php.');
        }

        return Reference::markdown(RootSchema::create(), 'Configuration reference', $matches[1]);
    }
}
