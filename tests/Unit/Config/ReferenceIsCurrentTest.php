<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Reference;
use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;

/**
 * Rule 5 of this project, made real: every configuration key exists in the
 * schema, with type, default, description and example, and the reference is
 * generated from it — so an undeclared key means an undocumented feature and
 * fails CI.
 *
 * The committed files are what a reader, and an agent writing configuration,
 * will actually open. They are only worth anything if they cannot drift, so
 * this compares each against what its schema would produce today. The list of
 * what exists lives in bin/reference-targets.php and is read by both this
 * test and the generator — a second copy here would let the two disagree
 * while this still passed.
 */
#[CoversNothing]
final class ReferenceIsCurrentTest extends TestCase
{
    /** @return list<array{file: string, schema: Schema, title: string, intro: string}> */
    private static function targets(): array
    {
        /** @var list<array{file: string, schema: Schema, title: string, intro: string}> $targets */
        $targets = require \dirname(__DIR__, 3) . '/bin/reference-targets.php';

        return $targets;
    }

    /** @return array<string, array{string}> */
    public static function referenceFiles(): array
    {
        $cases = [];

        foreach (self::targets() as $target) {
            $cases[$target['file']] = [$target['file']];
        }

        return $cases;
    }

    public function testEveryReferenceFileIsAccountedFor(): void
    {
        // A target list that lost an entry would silently stop checking that
        // file, and the file would sit on disk going stale.
        $listed = array_map(static fn (array $t): string => $t['file'], self::targets());
        $onDisk = array_map(
            'basename',
            glob(\dirname(__DIR__, 3) . '/docs/reference/*.md') ?: [],
        );

        sort($listed);
        sort($onDisk);

        $this->assertSame($onDisk, $listed, 'docs/reference/ and bin/reference-targets.php disagree.');
    }

    #[DataProvider('referenceFiles')]
    public function testTheCommittedReferenceMatchesItsSchema(string $file): void
    {
        $target = null;

        foreach (self::targets() as $candidate) {
            if ($candidate['file'] === $file) {
                $target = $candidate;
            }
        }

        $this->assertNotNull($target);

        $path = \dirname(__DIR__, 3) . '/docs/reference/' . $file;

        $this->assertFileExists($path, 'Run `composer run docs:reference`.');

        $committed = file_get_contents($path) ?: '';
        $generated = Reference::markdown($target['schema'], $target['title'], $target['intro']);

        $this->assertSame(
            str_replace("\r\n", "\n", $generated),
            str_replace("\r\n", "\n", $committed),
            "docs/reference/{$file} is out of date. Run `composer run docs:reference` and commit the result.",
        );
    }

    #[DataProvider('referenceFiles')]
    public function testEveryDocumentedKeyCarriesADescriptionAndAnExample(string $file): void
    {
        // A row with an empty description documents that a key exists and
        // nothing else, which is the shape of documentation that makes an
        // agent guess. Types with a sensible single value — bool — need no
        // example, so only the description is required of them.
        $target = null;

        foreach (self::targets() as $candidate) {
            if ($candidate['file'] === $file) {
                $target = $candidate;
            }
        }

        $this->assertNotNull($target);

        foreach ($this->everyKey($target['schema']) as $path => $key) {
            $this->assertNotSame('', $key->description, "The key {$path} has no description.");

            if ($key->children === null && $key->each === null && $key->type->value !== 'bool') {
                $this->assertNotNull($key->example, "The key {$path} has no example.");
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
