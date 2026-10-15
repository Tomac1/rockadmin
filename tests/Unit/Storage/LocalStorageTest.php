<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Storage\FileLink;
use RockAdmin\Storage\LocalStorage;
use RockAdmin\Storage\StorageException;
use RockAdmin\Storage\StorageKey;

/**
 * Files in a directory, and the one thing that must not happen.
 *
 * Half of these are about keys that are not keys. A storage key reaches this
 * class from two directions -- freshly generated from an upload, and read back
 * out of a database column somebody else may have written -- and the second is
 * why `..` is refused rather than stripped: a key that could be flattened into
 * a safe one is a key where the row says one thing and the file is another.
 */
#[CoversClass(LocalStorage::class)]
#[CoversClass(StorageKey::class)]
final class LocalStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-files-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);

        parent::tearDown();
    }

    public function testAFileGoesInAndComesBack(): void
    {
        $storage = $this->storage();

        $storage->put('ads/2026/10/8f3a.jpg', 'the-bytes', 'image/jpeg');

        self::assertTrue($storage->exists('ads/2026/10/8f3a.jpg'));
        self::assertSame('the-bytes', $storage->get('ads/2026/10/8f3a.jpg'));
        self::assertFileExists($this->root . '/ads/2026/10/8f3a.jpg');
    }

    public function testTheDirectoriesAreMadeOnTheWay(): void
    {
        self::assertDirectoryDoesNotExist($this->root);

        $this->storage()->put('ads/2026/10/8f3a.jpg', 'x', 'image/jpeg');

        self::assertDirectoryExists($this->root . '/ads/2026/10');
    }

    public function testWritingTwiceReplaces(): void
    {
        $storage = $this->storage();

        $storage->put('a/b.jpg', 'first', 'image/jpeg');
        $storage->put('a/b.jpg', 'second', 'image/jpeg');

        self::assertSame('second', $storage->get('a/b.jpg'));
    }

    public function testDeletingSomethingThatIsNotThereIsNotAFailure(): void
    {
        // It happens whenever a row is deleted twice, whenever a replaced file
        // was swept up by something else, and whenever an earlier deletion
        // half succeeded. Raising would mean every caller writing the same
        // try/catch.
        $this->expectNotToPerformAssertions();

        $this->storage()->delete('a/never-existed.jpg');
    }

    public function testDeletingRemovesIt(): void
    {
        $storage = $this->storage();
        $storage->put('a/b.jpg', 'x', 'image/jpeg');

        $storage->delete('a/b.jpg');

        self::assertFalse($storage->exists('a/b.jpg'));
    }

    public function testReadingSomethingThatIsNotThereSaysWhichKey(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('a/missing.jpg');

        $this->storage()->get('a/missing.jpg');
    }

    // --- keys that are not keys ---

    /**
     * The one that matters. Every one of these is a path somebody could put in
     * a column, and none of them may become a file outside the directory.
     *
     * @return iterable<string, array{string}>
     */
    public static function badKeys(): iterable
    {
        yield 'parent' => ['../outside.jpg'];
        yield 'parent in the middle' => ['ads/../../outside.jpg'];
        yield 'absolute' => ['/etc/passwd'];
        yield 'windows absolute' => ['c:/windows/system.ini'];
        yield 'backslash' => ['ads\\..\\outside.jpg'];
        yield 'empty' => [''];
        yield 'empty segment' => ['ads//b.jpg'];
        yield 'trailing slash' => ['ads/'];
        yield 'all dots' => ['...'];
        yield 'nul byte' => ["ads/b.jpg\0.php"];
        yield 'newline' => ["ads/b\n.jpg"];
        yield 'uppercase' => ['Ads/B.JPG'];
        yield 'space' => ['ads/my photo.jpg'];
    }

    #[DataProvider('badKeys')]
    public function testAKeyThatIsNotOneIsRefusedRatherThanRepaired(string $key): void
    {
        self::assertFalse(StorageKey::isValid($key), "'{$key}' was accepted as a key.");
    }

    #[DataProvider('badKeys')]
    public function testWritingWithSuchAKeyWritesNothingAnywhere(string $key): void
    {
        try {
            $this->storage()->put($key, 'x', 'image/jpeg');

            self::fail("'{$key}' was written.");
        } catch (StorageException $e) {
            self::assertStringContainsString('not a storage key', $e->getMessage());
        }

        self::assertFileDoesNotExist(\dirname($this->root) . '/outside.jpg');
    }

    public function testAMessageAboutABadKeyCarriesNoControlCharacters(): void
    {
        // The key is quoted into a message somebody may paste into a terminal.
        try {
            $this->storage()->get("ads/b\e[2J.jpg");

            self::fail('That key was accepted.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString("\e", $e->getMessage());
        }
    }

    public function testAKeyThisProjectWritesIsAccepted(): void
    {
        self::assertTrue(StorageKey::isValid('ads/2026/10/8f3a2b1c9d.jpg'));
        self::assertTrue(StorageKey::isValid('avatars/8f3a2b1c9d.webp'));
        self::assertTrue(StorageKey::isValid('one.pdf'));
    }

    // --- where a browser is sent ---

    public function testByDefaultAFileIsServedThroughTheAdminsOwnRoute(): void
    {
        $url = $this->storage()->url('ads/2026/10/8f3a.jpg');

        // The path is the route's; the query is the signature, which
        // `FileRouteTest` is about. Here it is enough that a private URL is
        // never handed out without one.
        self::assertStringStartsWith('/admin/_file/ads/2026/10/8f3a.jpg?', $url);
        self::assertMatchesRegularExpression('/[?&]s=[0-9a-f]{32}(&|$)/', $url);
    }

    public function testAStorageWithAUrlGeneratorAndNoSignerStillRefuses(): void
    {
        // A signature is not decoration: a private link that anybody signed
        // in could follow is the hole the signer exists to close, so leaving
        // the signer out is the same mistake as leaving the generator out.
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('no way to build a URL');

        (new LocalStorage($this->root, new UrlGenerator('/admin')))->url('ads/8f3a.jpg');
    }

    public function testAPublicBaseUrlWinsAndIsUsedVerbatim(): void
    {
        $storage = new LocalStorage($this->root, new UrlGenerator('/admin'), 'https://cdn.example.com/up/');

        self::assertSame('https://cdn.example.com/up/ads/8f3a.jpg', $storage->url('ads/8f3a.jpg'));
    }

    public function testAStorageWiredWithNeitherSaysSoRatherThanGuessing(): void
    {
        // Written and never showable is the failure this catches, and it is
        // one somebody would otherwise meet after uploading.
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('no way to build a URL');

        (new LocalStorage($this->root))->url('ads/8f3a.jpg');
    }

    private function storage(): LocalStorage
    {
        return new LocalStorage(
            $this->root,
            new UrlGenerator('/admin'),
            '',
            new FileLink(new ArraySessionStore()),
        );
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
