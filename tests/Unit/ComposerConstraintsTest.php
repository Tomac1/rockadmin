<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * RockAdmin's defining constraint is that it has no runtime dependencies
 * beyond PHP itself and a handful of core extensions. This test enforces it,
 * so a dependency cannot be added without deliberately changing this file.
 *
 * See docs/superpowers/specs/2026-09-21-rockadmin-design.md, section 3.
 */
#[CoversNothing]
final class ComposerConstraintsTest extends TestCase
{
    /** @var list<string> */
    private const ALLOWED_REQUIRES = [
        'php',
        'ext-pdo',
        'ext-json',
        'ext-mbstring',
    ];

    public function testRuntimeRequiresOnlyPhpAndCoreExtensions(): void
    {
        $unexpected = array_diff(array_keys($this->requireSection()), self::ALLOWED_REQUIRES);

        $this->assertSame(
            [],
            array_values($unexpected),
            'RockAdmin must not gain runtime dependencies. Adding one requires an explicit '
            . 'decision, an update to this test, and an update to the design specification.',
        );
    }

    public function testMinimumPhpVersionIsDeclared(): void
    {
        $this->assertSame('^8.4', $this->requireSection()['php'] ?? null);
    }

    /**
     * @return array<string, mixed> the "require" section of composer.json
     */
    private function requireSection(): array
    {
        $path = \dirname(__DIR__, 2) . '/composer.json';
        $raw = file_get_contents($path);

        if (!\is_string($raw)) {
            self::fail("composer.json is unreadable at {$path}.");
        }

        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded) || !\is_array($decoded['require'] ?? null)) {
            self::fail('composer.json has no "require" section.');
        }

        /** @var array<string, mixed> $require */
        $require = $decoded['require'];

        return $require;
    }
}
