<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\RouteAccess;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\FileHandler;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Storage\FileLink;
use RockAdmin\Storage\LocalStorage;
use RockAdmin\Storage\Storage;

/**
 * Fetching an uploaded file, and the four ways that is refused.
 *
 * The claim under test is the one the plan was amended for: **a key is not a
 * permission.** `Gate` makes the route authenticated, which keeps strangers
 * out and says nothing about one administrator and another's file; the
 * signature is what says this link was made for this session, minutes ago.
 * So the tests here are mostly refusals, and each of them is a thing that
 * would otherwise work:
 *
 * - a key with no signature at all;
 * - a signature made in somebody else's session;
 * - one made long enough ago to have expired;
 * - a path trying to leave the storage, which never reaches a driver.
 */
#[CoversClass(FileHandler::class)]
#[CoversClass(FileLink::class)]
final class FileRouteTest extends TestCase
{
    private string $root;

    private SessionStore $session;

    private Storage $files;

    private FileLink $links;

    private string $key = 'ads/2026/10/0123456789abcdef0123456789abcdef.png';

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-file-' . bin2hex(random_bytes(6));
        $this->session = new ArraySessionStore();
        $this->links = new FileLink($this->session);
        $this->files = new LocalStorage($this->root, new UrlGenerator('/admin'), '', $this->links);
        $this->files->put($this->key, self::png(), 'image/png');
    }

    protected function tearDown(): void
    {
        self::remove($this->root);
    }

    private static function png(): string
    {
        // A one-pixel PNG, signature and all, because the storage is real.
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ) ?: '';
    }

    private static function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::remove($path . '/' . $entry);
                }
            }

            @rmdir($path);

            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @param array<string, mixed> $query */
    private function get(string $key, array $query = [], ?FileLink $links = null): \RockAdmin\Http\Response
    {
        $handler = new FileHandler($this->files, $links ?? $this->links);

        return $handler->handle(
            new Route('file', ['key' => $key]),
            new Request('GET', '/_file/' . $key, $query),
        );
    }

    /**
     * The query a signed URL carries, read back off the URL the storage
     * built -- so these tests follow the link a page would have drawn rather
     * than signing one of their own.
     *
     * @return array<string, mixed>
     */
    private function signed(?string $key = null): array
    {
        $url = $this->files->url($key ?? $this->key);
        $query = parse_url($url, PHP_URL_QUERY);

        self::assertIsString($query, 'A private file URL is expected to carry a signature.');

        // `parse_str()` writes whatever shape the string describes, so the
        // values are narrowed here rather than declared -- which is also the
        // shape `Request` would have received them in.
        parse_str($query, $parsed);

        $values = [];

        foreach ($parsed as $name => $value) {
            $values[(string) $name] = $value;
        }

        return $values;
    }

    // -- the link that works ------------------------------------------

    public function testASignedLinkServesTheFileWithTheTypeItsKeySays(): void
    {
        $response = $this->get($this->key, $this->signed());

        self::assertSame(200, $response->status);
        self::assertSame(self::png(), $response->body);
        self::assertSame('image/png', $response->headers['content-type']);
        self::assertStringStartsWith('inline;', $response->headers['content-disposition']);
        self::assertSame('nosniff', $response->headers['x-content-type-options']);
        self::assertSame('private, no-store', $response->headers['cache-control']);
    }

    public function testTheSignedUrlIsBuiltFromTheRouteAndCarriesBothParameters(): void
    {
        $url = $this->files->url($this->key);

        self::assertStringStartsWith('/admin/_file/' . $this->key . '?', $url);

        $query = $this->signed();

        self::assertArrayHasKey(FileLink::EXPIRY, $query);
        self::assertArrayHasKey(FileLink::SIGNATURE, $query);
        $expiry = $query[FileLink::EXPIRY];

        self::assertIsNumeric($expiry);
        self::assertGreaterThan(time(), (int) $expiry);
    }

    // -- the refusals --------------------------------------------------

    public function testTheRouteIsClosedToAnyoneNotSignedIn(): void
    {
        // The floor the signature sits on: `Kernel` asks this before a
        // handler is reached at all.
        self::assertSame(RouteAccess::Authenticated, Gate::accessFor('file'));
        self::assertNotSame(RouteAccess::Anonymous, Gate::accessFor('file'));
    }

    public function testAKeyWithNoSignatureIsRefusedEvenThoughTheFileIsThere(): void
    {
        self::assertTrue($this->files->exists($this->key));

        $this->expectException(ForbiddenException::class);

        $this->get($this->key);
    }

    public function testASignatureFromAnotherSessionDoesNotWorkInThisOne(): void
    {
        $query = $this->signed();

        // Another person, another session, and therefore another secret --
        // which is the whole mechanism: there is no identity compared here,
        // because the signature simply cannot verify.
        $other = new FileLink(new ArraySessionStore());

        $this->expectException(ForbiddenException::class);

        $this->get($this->key, $query, $other);
    }

    public function testAnExpiredSignatureIsRefused(): void
    {
        // Signed with a lifetime that has already run out, which is the same
        // arithmetic a link followed an hour later produces.
        $stale = new FileLink($this->session, -60);
        $query = $stale->queryFor($this->key);

        self::assertFalse($this->links->isValid(
            $this->key,
            $query[FileLink::EXPIRY],
            $query[FileLink::SIGNATURE],
        ));

        $this->expectException(ForbiddenException::class);

        $this->get($this->key, $query);
    }

    public function testASignatureForOneKeyDoesNotOpenAnother(): void
    {
        $other = 'ads/2026/10/ffffffffffffffffffffffffffffffff.png';
        $this->files->put($other, self::png(), 'image/png');

        $this->expectException(ForbiddenException::class);

        $this->get($other, $this->signed($this->key));
    }

    #[DataProvider('tamperedProvider')]
    public function testATamperedSignatureIsRefused(mixed $expiry, mixed $signature): void
    {
        $this->expectException(ForbiddenException::class);

        $this->get($this->key, [FileLink::EXPIRY => $expiry, FileLink::SIGNATURE => $signature]);
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function tamperedProvider(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'an expiry that is not a number' => ['soon', str_repeat('a', 32)];
        yield 'a number with something after it' => ['9999999999abc', str_repeat('a', 32)];
        yield 'an array where a string goes' => [['9999999999'], ['a']];
        yield 'a signature of the right length' => ['9999999999', str_repeat('0', 32)];
        yield 'no signature at all' => ['9999999999', null];
    }

    // -- a path that must never reach a driver --------------------------

    #[DataProvider('traversalProvider')]
    public function testAKeyTryingToLeaveTheStorageIsRefusedBeforeAnythingIsRead(string $key): void
    {
        // A recording storage, so the test can say the driver was never
        // asked -- "it answered 404" would also be true of a driver that
        // looked, which is the thing this is about.
        $storage = new class () implements Storage {
            public bool $asked = false;

            public function put(string $key, string $contents, string $contentType): void
            {
                $this->asked = true;
            }

            public function get(string $key): string
            {
                $this->asked = true;

                return '';
            }

            public function delete(string $key): void
            {
                $this->asked = true;
            }

            public function exists(string $key): bool
            {
                $this->asked = true;

                return false;
            }

            public function url(string $key): string
            {
                $this->asked = true;

                return '';
            }

            public function accepts(string $key): bool
            {
                return \RockAdmin\Storage\StorageKey::isValid($key);
            }
        };

        $handler = new FileHandler($storage, $this->links);

        try {
            $handler->handle(
                new Route('file', ['key' => $key]),
                new Request('GET', '/_file/' . $key, $this->signed()),
            );
            self::fail('Expected the key to be refused.');
        } catch (NotFoundException) {
            // the refusal under test
        }

        self::assertFalse($storage->asked, 'The storage must never be asked about such a key.');
    }

    /** @return iterable<string, array{string}> */
    public static function traversalProvider(): iterable
    {
        yield 'a parent directory' => ['ads/../../etc/passwd'];
        yield 'a bare traversal' => ['../rockadmin.php'];
        yield 'an absolute path' => ['/etc/passwd'];
        yield 'a windows path' => ['ads\\..\\..\\boot.ini'];
        yield 'a null byte' => ["ads/photo.png\0.txt"];
        yield 'a stream wrapper' => ['php://input'];
        yield 'nothing at all' => [''];
    }

    // -- what a file that is not an image gets --------------------------

    public function testAFileThatIsNotAnImageIsHandedOverAsADownload(): void
    {
        $key = 'ads/2026/10/00000000000000000000000000000001.pdf';
        $this->files->put($key, '%PDF-1.4 ', 'application/pdf');

        $response = $this->get($key, $this->signed($key));

        self::assertSame('application/pdf', $response->headers['content-type']);
        self::assertStringStartsWith('attachment;', $response->headers['content-disposition']);
    }

    public function testAKeyWhoseFileHasGoneIsANotFound(): void
    {
        $gone = 'ads/2026/10/00000000000000000000000000000002.png';
        $query = $this->signed($gone);

        $this->expectException(NotFoundException::class);

        $this->get($gone, $query);
    }
}
