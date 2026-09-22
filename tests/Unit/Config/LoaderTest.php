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

    public function testWarningsFromEnumsAreCollectedToo(): void
    {
        $loader = $this->loader('warnings', ['PROVIDER_API_KEY' => 'secret-value']);
        $loader->load();

        $this->assertCount(1, $loader->warnings());
        $this->assertStringContainsString('PROVIDER_API_KEY', $loader->warnings()[0]);
    }

    public function testAFileThatDoesNotReturnAnArrayIsRefusedByName(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('rockadmin.php');

        $this->loader('not-an-array')->load();
    }
}
