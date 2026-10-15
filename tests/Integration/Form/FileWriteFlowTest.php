<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\Sql;
use RockAdmin\Db\SqlRowSource;
use RockAdmin\Db\SqlWriteHandler;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FileWrites;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Http\ActionHandler;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\FormHandler;
use RockAdmin\Http\PayloadTooLargeException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UploadedFile;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\PageRepository;
use RockAdmin\Storage\LocalStorage;
use RockAdmin\Tests\Support\Access;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * What happens to the bytes when a row is written, and when it is not.
 *
 * The ordering is the whole subject, and it is a trade rather than a win:
 *
 * - a file has to exist before a row can point at it, so it is stored
 *   **before** the write — which means a write that fails leaves bytes nobody
 *   references, and every refusing path has to sweep them up;
 * - a file is deleted **after** the commit, because a write can still be
 *   rolled back and a file that was deleted for a row which then stayed
 *   exactly as it was is gone for good.
 *
 * So: a failed save leaves nothing behind, and a successful one leaves at
 * most an orphan if the deletion itself fails. Both halves are asserted here,
 * against a real database and a real directory.
 */
#[CoversClass(FileWrites::class)]
#[CoversClass(ActionHandler::class)]
final class FileWriteFlowTest extends DatabaseTestCase
{
    private string $root;

    private string $storageRoot;

    private SessionStore $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new ArraySessionStore();
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-filewrite-' . bin2hex(random_bytes(6));
        $this->storageRoot = $this->root . '/uploads';
        mkdir($this->root . '/pages', 0o777, true);
        $this->writePage();

        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->createFixture($connection);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $connection->execute(new Sql('DROP TABLE IF EXISTS ra_test_photos'));
                $this->createFixtures($connection);
            }
        }

        $this->remove($this->root);

        parent::tearDown();
    }

    #[DataProvider('connections')]
    public function testCreatingWithAFileStoresItAndPutsItsKeyInTheColumn(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());

        self::assertSame(302, $response->status, $response->body);

        $key = $this->storedKey($connection);

        self::assertMatchesRegularExpression('~^photos/\d{4}/\d{2}/[a-f0-9]{32}\.png$~', $key);
        self::assertFileExists($this->storageRoot . '/' . $key);
        self::assertSame(self::png(), file_get_contents($this->storageRoot . '/' . $key));
    }

    #[DataProvider('connections')]
    public function testTheColumnHoldsAKeyAndNotAUrl(?Connection $connection): void
    {
        // A stored URL is a stored decision about the bucket, the CDN domain
        // and whether the file is public -- and a signed one expires.
        $connection = $this->requireConnection($connection);

        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());

        self::assertStringNotContainsString('http', $this->storedKey($connection));
        self::assertStringNotContainsString('_file', $this->storedKey($connection));
    }

    #[DataProvider('connections')]
    public function testAnEditThatUploadsNothingKeepsTheFile(?Connection $connection): void
    {
        // An empty file input means "keep what is stored", which is what a
        // browser sends for a control nobody touched.
        $connection = $this->requireConnection($connection);
        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());

        $before = $this->storedKey($connection);
        $id = $this->idOf($connection);

        $response = $this->post($connection, 'update', [FormFields::ID => $id, 'title' => 'Silniční kolo']);

        self::assertSame(302, $response->status, $response->body);
        self::assertSame($before, $this->storedKey($connection), 'An edit cleared the photograph.');
        self::assertFileExists($this->storageRoot . '/' . $before);
    }

    #[DataProvider('connections')]
    public function testReplacingAFileDeletesTheOldOneAfterTheCommit(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());

        $first = $this->storedKey($connection);
        $id = $this->idOf($connection);

        $this->post($connection, 'update', [FormFields::ID => $id, 'title' => 'Horské kolo'], $this->upload(self::gif()));

        $second = $this->storedKey($connection);

        self::assertNotSame($first, $second);
        self::assertStringEndsWith('.gif', $second);
        self::assertFileExists($this->storageRoot . '/' . $second);
        self::assertFileDoesNotExist($this->storageRoot . '/' . $first, 'The replaced file is still there.');
    }

    #[DataProvider('connections')]
    public function testTickingRemoveClearsTheColumnAndTheFile(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());

        $key = $this->storedKey($connection);
        $id = $this->idOf($connection);

        $this->post($connection, 'update', [
            FormFields::ID => $id,
            'title' => 'Horské kolo',
            FormFields::removeFor('photo') => '1',
        ]);

        self::assertSame('', $this->storedKey($connection));
        self::assertFileDoesNotExist($this->storageRoot . '/' . $key);
    }

    #[DataProvider('connections')]
    public function testDeletingTheRowTakesItsFileWithIt(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());

        $key = $this->storedKey($connection);

        $this->post($connection, 'delete', [FormFields::ID => $this->idOf($connection)]);

        self::assertSame([], $connection->select(new Sql('SELECT id FROM ra_test_photos')));
        self::assertFileDoesNotExist($this->storageRoot . '/' . $key);
    }

    /** The other half of the ordering: a write that never happened leaves nothing. */
    #[DataProvider('connections')]
    public function testAWriteRefusedByTheDatabaseLeavesNoFile(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());

        // `title` is unique in the fixture, so this one is refused by the
        // index -- after the file has already been stored.
        $response = $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload(self::gif()));

        self::assertSame(422, $response->status);
        self::assertCount(1, $this->filesOnDisk(), 'A refused save left its upload behind.');
    }

    #[DataProvider('connections')]
    public function testAWriteRefusedByValidationLeavesNoFile(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        // `title` is required, so this is refused before anything is written
        // -- and the upload that came with it must not survive.
        $response = $this->post($connection, 'create', ['title' => ''], $this->upload());

        self::assertSame(422, $response->status);
        self::assertSame([], $this->filesOnDisk());
    }

    #[DataProvider('connections')]
    public function testAFileThePageWillNotTakeIsRefusedAndStoredNowhere(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->post(
            $connection,
            'create',
            ['title' => 'Horské kolo'],
            $this->upload('<?php system($_GET["c"]); ?>', 'kolo.jpg'),
        );

        self::assertSame(422, $response->status);
        self::assertStringContainsString('not a kind of file this page takes', $response->body);
        self::assertSame([], $this->filesOnDisk());
        self::assertSame([], $connection->select(new Sql('SELECT id FROM ra_test_photos')));
    }

    /**
     * PHP discards the body of a POST over `post_max_size`: no fields, no
     * files, nothing raised. The request then looks exactly like a form
     * submitted with no CSRF token -- so the first version of this check sat
     * inside `save()`, where the token check had already turned it into "this
     * form was open too long", and this test is what said so.
     *
     * It is answered before the token now, which is the only place it can be
     * answered, because the token is part of what went missing.
     */
    #[DataProvider('connections')]
    public function testAnOversizedBodyIsReportedAsASizeProblemRatherThanAStaleForm(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        try {
            $this->actions($connection)->handle(
                new Route('action', ['page' => 'photos', 'action' => 'create']),
                new Request(
                    'POST',
                    'a/photos/create',
                    headers: ['Content-Type' => 'multipart/form-data; boundary=----x'],
                    contentLength: 80_000_000,
                ),
            );

            self::fail('An oversized upload was accepted.');
        } catch (PayloadTooLargeException $e) {
            self::assertSame(413, $e->status);
            self::assertStringContainsString('larger than this server accepts', $e->getMessage());
            self::assertStringContainsString('post_max_size', $e->getMessage(), 'Nothing to act on.');
        }
    }

    /**
     * The other half of the same claim, and the one that would have caught
     * the original mistake on its own: whatever else this answers, it must
     * not be the 403 that sends somebody back to the login form.
     */
    #[DataProvider('connections')]
    public function testAnOversizedBodyIsNotMistakenForAFailedToken(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        try {
            $this->actions($connection)->handle(
                new Route('action', ['page' => 'photos', 'action' => 'create']),
                new Request(
                    'POST',
                    'a/photos/create',
                    headers: ['Content-Type' => 'multipart/form-data; boundary=----x'],
                    contentLength: 80_000_000,
                ),
            );

            self::fail('An oversized upload was accepted.');
        } catch (ForbiddenException $e) {
            self::fail('An oversized upload was reported as a CSRF failure: ' . $e->getMessage());
        } catch (PayloadTooLargeException $e) {
            self::assertSame(413, $e->status);
        }
    }

    /**
     * A key belonging somewhere else is not this page's to destroy.
     *
     * Found by a security review as an asymmetry rather than as an exploit,
     * and that is the right way to read it: `FormRegion` already refused to
     * *show* a file whose key was not under this page's folder, while
     * deleting one went on the shape of the key alone -- the weaker check on
     * the more destructive act. A column can hold such a key after an
     * injection, a hand-edited database or an import from an older version,
     * and none of those is a reason to delete somebody else's file.
     */
    #[DataProvider('connections')]
    public function testAKeyFromAnotherPageIsNotDeletedWhenTheFileIsReplaced(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $foreign = $this->foreignFile();

        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());
        $id = $this->idOf($connection);

        // As if the column had been pointed at another page's file.
        $connection->execute(new Sql('UPDATE ra_test_photos SET photo = ? WHERE id = ?', [$foreign, $id]));

        $this->post($connection, 'update', [FormFields::ID => $id, 'title' => 'Horské kolo'], $this->upload(self::gif()));

        self::assertFileExists(
            $this->storageRoot . '/' . $foreign,
            "Replacing a photograph deleted another page's file.",
        );
    }

    #[DataProvider('connections')]
    public function testAKeyFromAnotherPageSurvivesTheRowBeingDeleted(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $foreign = $this->foreignFile();

        $this->post($connection, 'create', ['title' => 'Horské kolo'], $this->upload());
        $id = $this->idOf($connection);
        $connection->execute(new Sql('UPDATE ra_test_photos SET photo = ? WHERE id = ?', [$foreign, $id]));

        $this->post($connection, 'delete', [FormFields::ID => $id]);

        self::assertSame([], $connection->select(new Sql('SELECT id FROM ra_test_photos')));
        self::assertFileExists(
            $this->storageRoot . '/' . $foreign,
            'Deleting a row deleted a file belonging to another page.',
        );
    }

    /** A file stored under another page's folder, as that page would have written it. */
    private function foreignFile(): string
    {
        $key = 'invoices/2026/10/' . bin2hex(random_bytes(16)) . '.pdf';

        (new LocalStorage($this->storageRoot))->put($key, self::png(), 'image/png');

        return $key;
    }

    // --- harness ---

    /** @param array<string, mixed> $body */
    private function post(
        Connection $connection,
        string $verb,
        array $body,
        ?UploadedFile $file = null,
    ): Response {
        return $this->actions($connection)->handle(
            new Route('action', ['page' => 'photos', 'action' => $verb]),
            new Request(
                'POST',
                "a/photos/{$verb}",
                body: [Csrf::FIELD => (new Csrf($this->session))->token()] + $body,
                files: $file === null ? [] : ['photo' => $file],
            ),
        );
    }

    private function actions(Connection $connection): ActionHandler
    {
        $urls = new UrlGenerator('/admin');
        $csrf = new Csrf($this->session);
        $flashes = new FlashBag($this->session);
        $reader = new RowReader(new SqlRowSource($connection));
        $storage = new LocalStorage($this->storageRoot, $urls);
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);
        $region = new FormRegion(
            $reader,
            $urls,
            $csrf,
            Access::gateFor($this->repository()),
            Access::identity(),
            null,
            $storage,
        );

        return new ActionHandler(
            $this->repository(),
            $region,
            new FieldValidator(),
            new SqlWriteHandler($connection),
            $reader,
            null,
            $connection,
            new FormHandler(
                $this->repository(),
                $region,
                $renderer,
                new Assets($urls),
                $flashes,
                $urls,
                'RockAdmin',
                'auto',
            ),
            $csrf,
            $flashes,
            $urls,
            null,
            Access::identity()->user(),
            null,
            null,
            null,
            new FileWrites($storage),
        );
    }

    private function upload(string $contents = '', string $name = 'kolo.png'): UploadedFile
    {
        $contents = $contents === '' ? self::png() : $contents;
        $path = tempnam(sys_get_temp_dir(), 'ra-upload-');

        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($name, $path, \strlen($contents));
    }

    private function storedKey(Connection $connection): string
    {
        $rows = $connection->select(new Sql('SELECT photo FROM ra_test_photos ORDER BY id'));
        $value = $rows[0]['photo'] ?? null;

        return \is_string($value) ? $value : '';
    }

    private function idOf(Connection $connection): string
    {
        $rows = $connection->select(new Sql('SELECT id FROM ra_test_photos ORDER BY id'));
        $value = $rows[0]['id'] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /** @return list<string> */
    private function filesOnDisk(): array
    {
        $found = [];
        $directory = $this->storageRoot;

        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    private function repository(): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            Enums::fromConfig([]),
        );
    }

    private function createFixture(Connection $connection): void
    {
        $connection->execute(new Sql('DROP TABLE IF EXISTS ra_test_photos'));

        $key = $connection->dialect()->name() === 'mysql'
            ? 'INTEGER AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';

        $connection->execute(new Sql(
            "CREATE TABLE ra_test_photos (id {$key}, title VARCHAR(200) NOT NULL UNIQUE, "
            . 'photo VARCHAR(255))',
        ));
    }

    private function writePage(): void
    {
        file_put_contents($this->root . '/pages/photos.php', <<<'PHP'
            <?php

            return [
                'title' => 'Photos',
                'entity' => ['table' => 'ra_test_photos'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => [
                                'title' => ['type' => 'text', 'required' => true],
                                'photo' => ['type' => 'file', 'accept' => ['image/png', 'image/gif']],
                            ],
                        ],
                    ],
                ],
            ];
            PHP);
    }

    private static function png(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }

    private static function gif(): string
    {
        return (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }
}
