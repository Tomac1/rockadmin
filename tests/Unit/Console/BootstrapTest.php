<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Console\Bootstrap;
use RockAdmin\Console\ConsoleException;
use RockAdmin\Console\Input;

/**
 * Where the command line looks for the bootstrap file.
 *
 * Under Laravel the default `config/rockadmin/` cannot be used: Laravel loads
 * every file under `config/` as its own configuration, so it runs the
 * bootstrap and `config:cache` fails on the closures in the `Setup`. A
 * project that moves the file needs a way to say where it went that a deploy
 * script does not have to repeat on every command.
 */
#[CoversClass(Bootstrap::class)]
final class BootstrapTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-bootstrap-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/composer.json');
        @rmdir($this->root);
    }

    public function testTheDefaultIsConfigRockadmin(): void
    {
        self::assertSame(
            $this->root . '/config/rockadmin/bootstrap.php',
            Bootstrap::locate(new Input([]), $this->root, null),
        );
    }

    public function testComposerExtraIsReadRelativeToTheProject(): void
    {
        $this->composer(['extra' => ['rockadmin' => ['bootstrap' => 'admin/bootstrap.php']]]);

        self::assertSame($this->root . '/admin/bootstrap.php', Bootstrap::locate(new Input([]), $this->root, null));
    }

    public function testTheEnvironmentWinsOverComposer(): void
    {
        $this->composer(['extra' => ['rockadmin' => ['bootstrap' => 'admin/bootstrap.php']]]);

        self::assertSame(
            $this->root . '/elsewhere/bootstrap.php',
            Bootstrap::locate(new Input([]), $this->root, 'elsewhere/bootstrap.php'),
        );
    }

    public function testTheOptionWinsOverEverything(): void
    {
        $this->composer(['extra' => ['rockadmin' => ['bootstrap' => 'admin/bootstrap.php']]]);

        self::assertSame(
            '/abs/bootstrap.php',
            Bootstrap::locate(new Input(['--bootstrap=/abs/bootstrap.php']), $this->root, 'env/bootstrap.php'),
        );
    }

    public function testAComposerFileWithoutTheKeyFallsBackToTheDefault(): void
    {
        $this->composer(['name' => 'acme/app', 'extra' => ['laravel' => []]]);

        self::assertSame(
            $this->root . '/config/rockadmin/bootstrap.php',
            Bootstrap::locate(new Input([]), $this->root, null),
        );
    }

    public function testAMalformedComposerKeyIsRefusedRatherThanIgnored(): void
    {
        $this->composer(['extra' => ['rockadmin' => ['bootstrap' => ['admin']]]]);

        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('extra.rockadmin.bootstrap');

        Bootstrap::locate(new Input([]), $this->root, null);
    }

    public function testTheMissingFileMessageNamesEveryWayToPointAtOne(): void
    {
        try {
            Bootstrap::setup(new Input([]), $this->root);
            self::fail('Expected a ConsoleException.');
        } catch (ConsoleException $e) {
            self::assertStringContainsString('--bootstrap', $e->getMessage());
            self::assertStringContainsString('RA_BOOTSTRAP', $e->getMessage());
            self::assertStringContainsString('extra.rockadmin.bootstrap', $e->getMessage());
        }
    }

    /** @param array<string, mixed> $data */
    private function composer(array $data): void
    {
        file_put_contents($this->root . '/composer.json', json_encode($data, \JSON_PRETTY_PRINT));
    }
}
