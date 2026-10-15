<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Console;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Console\DoctorCommand;
use RockAdmin\Console\InitCommand;
use RockAdmin\Console\Input;
use RockAdmin\Console\MakePageCommand;
use RockAdmin\Console\MigrateCommand;
use RockAdmin\Console\Output;
use RockAdmin\Console\UserCreateCommand;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;
use RockAdmin\Setup;

/**
 * The commands, against a real directory and a real database.
 *
 * What is checked is the path a first install actually walks — `init`,
 * `migrate`, `user:create`, `doctor` — in that order, because that order is
 * the product: each command is only correct in terms of what the one before
 * it left behind, and testing them apart would miss exactly the join that
 * broke when `doctor` asked `ra_users` a question before `migrate` had
 * created it.
 */
final class CommandsTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-cli-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testInitWritesTheFilesAProjectCommitsAndNeverOverwritesThem(): void
    {
        $output = $this->silent();

        self::assertSame(0, (new InitCommand())->run(new Input(['--path=' . $this->root]), $output));

        $config = $this->root . '/config/rockadmin';

        self::assertFileExists($config . '/rockadmin.php');
        self::assertFileExists($config . '/roles.php');
        self::assertFileExists($config . '/bootstrap.php');
        self::assertDirectoryExists($config . '/pages');

        // What it writes has to load: a scaffolder that writes a file the
        // admin refuses is worse than one that writes nothing, because the
        // first thing it does is make somebody doubt their own setup.
        $setup = require $config . '/bootstrap.php';
        self::assertInstanceOf(Setup::class, $setup);

        file_put_contents($config . '/roles.php', '<?php return [];');

        self::assertSame(0, (new InitCommand())->run(new Input(['--path=' . $this->root]), $output));
        self::assertSame('<?php return [];', file_get_contents($config . '/roles.php'));
    }

    #[DataProvider('servers')]
    public function testTheFirstInstallWalksInitMigrateUserCreateDoctor(?string $prefix): void
    {
        $connection = $this->connect($prefix);
        $this->dropTables($connection);

        (new InitCommand())->run(new Input(['--path=' . $this->root]), $this->silent());
        $this->writeBootstrap((string) $prefix);

        $input = new Input(['--bootstrap=' . $this->root . '/bootstrap-test.php']);

        [$code, $text] = $this->capture(static fn (Output $out): int => (new MigrateCommand())->run($input, $out));
        self::assertSame(0, $code, $text);
        self::assertStringContainsString('001_identity.sql', $text);

        // Twice is the normal case, not an error: a deploy runs it every time.
        [$code, $text] = $this->capture(static fn (Output $out): int => (new MigrateCommand())->run($input, $out));
        self::assertSame(0, $code);
        self::assertStringContainsString('up to date', $text);

        $create = new Input([
            '--bootstrap=' . $this->root . '/bootstrap-test.php',
            '--email=cli@example.com',
            '--name=CLI',
            '--role=superadmin',
            '--password=a-long-enough-password',
        ]);

        [$code, $text] = $this->capture(static fn (Output $out): int => (new UserCreateCommand())->run($create, $out));
        self::assertSame(0, $code, $text);
        self::assertStringContainsString('founder', $text);

        [$code, $text] = $this->capture(static fn (Output $out): int => (new DoctorCommand())->run($input, $out));
        self::assertSame(0, $code, $text);
        self::assertStringContainsString('Nothing to fix.', $text);

        $this->dropTables($connection);
    }

    #[DataProvider('servers')]
    public function testMakePageWritesAPageTheAdminAccepts(?string $prefix): void
    {
        $connection = $this->connect($prefix);
        $this->dropTables($connection);

        (new InitCommand())->run(new Input(['--path=' . $this->root]), $this->silent());
        $this->writeBootstrap((string) $prefix);

        $input = new Input(['--bootstrap=' . $this->root . '/bootstrap-test.php']);
        (new MigrateCommand())->run($input, $this->silent());

        $make = new Input([
            'admins',
            '--table=ra_users',
            '--bootstrap=' . $this->root . '/bootstrap-test.php',
        ]);

        [$code, $text] = $this->capture(static fn (Output $out): int => (new MakePageCommand())->run($make, $out));
        self::assertSame(0, $code, $text);

        $page = (string) file_get_contents($this->root . '/config/rockadmin/pages/admins.php');

        // A password column drawn in a grid is everybody's hash on a page,
        // and a password *field* over a column the page does not know to
        // hash writes the plaintext in. Found by scaffolding this very table.
        self::assertStringNotContainsString("'password' =>", $page);
        self::assertStringContainsString('Left out: password', $page);

        // The real check: the page the scaffolder wrote is one the admin
        // will load. It used to write keys the schema has never had.
        [$code, $text] = $this->capture(static fn (Output $out): int => (new DoctorCommand())->run($input, $out));
        self::assertStringNotContainsString('could not be started', $text);

        $this->dropTables($connection);
    }

    /** A bootstrap pointing at the test database, which is what the CLI loads. */
    private function writeBootstrap(string $prefix): void
    {
        [$dsnValue, $userValue, $passwordValue] = $this->dsn($prefix);
        $dsn = var_export($dsnValue, true);
        $user = var_export($userValue, true);
        $password = var_export($passwordValue, true);
        $root = var_export($this->root, true);

        file_put_contents($this->root . '/bootstrap-test.php', <<<PHP
            <?php

            return new RockAdmin\\Setup(
                basePath: {$root},
                configPath: {$root} . '/config/rockadmin',
                pdo: static fn (): PDO => new PDO({$dsn}, {$user}, {$password}),
            );
            PHP);
    }

    /** @return array{string, ?string, ?string} */
    private function dsn(string $prefix): array
    {
        $dsn = getenv("RA_TEST_{$prefix}_DSN");
        $user = getenv("RA_TEST_{$prefix}_USER");
        $password = getenv("RA_TEST_{$prefix}_PASSWORD");

        return [
            \is_string($dsn) ? $dsn : '',
            \is_string($user) ? $user : null,
            \is_string($password) ? $password : null,
        ];
    }

    /** Skips when nothing is configured, the way every database test here does. */
    private function connect(?string $prefix): Connection
    {
        if ($prefix === null) {
            self::markTestSkipped('No database configured. Set RA_TEST_MYSQL_DSN or RA_TEST_PGSQL_DSN.');
        }

        [$dsn, $user, $password] = $this->dsn($prefix);

        return Connection::fromPdo(new PDO($dsn, $user, $password));
    }

    private function dropTables(Connection $connection): void
    {
        foreach (['ra_login_attempts', 'ra_password_resets', 'ra_activity_log', 'ra_users', 'ra_migrations'] as $table) {
            try {
                $connection->execute(new Sql('DROP TABLE IF EXISTS ' . $table));
            } catch (\Throwable) {
                // A table this run never created is not a failure to clean up.
            }
        }
    }

    /**
     * @param  \Closure(Output): int $run
     * @return array{int, string}
     */
    private function capture(\Closure $run): array
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        $code = $run(new Output($stream, $stream, colour: false));

        rewind($stream);
        $text = stream_get_contents($stream);
        fclose($stream);

        return [$code, \is_string($text) ? $text : ''];
    }

    private function silent(): Output
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        return new Output($stream, $stream, colour: false);
    }

    private function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }

            @rmdir($path);

            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @return iterable<string, array{?string}> */
    public static function servers(): iterable
    {
        $any = false;

        foreach (['mysql' => 'MYSQL', 'pgsql' => 'PGSQL'] as $driver => $prefix) {
            $dsn = getenv("RA_TEST_{$prefix}_DSN");

            if (!\is_string($dsn) || $dsn === '') {
                continue;
            }

            $any = true;

            yield $driver => [$prefix];
        }

        if (!$any) {
            yield 'no database configured' => [null];
        }
    }
}
