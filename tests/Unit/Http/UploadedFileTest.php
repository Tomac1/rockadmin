<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\Request;
use RockAdmin\Http\UploadedFile;
use RockAdmin\Storage\FileTypes;

/**
 * An upload before anybody has believed anything about it.
 *
 * Three groups, and the middle one is the reason this milestone has a task of
 * its own for receiving a file:
 *
 * 1. the shapes `$_FILES` arrives in, including the one that means "nobody
 *    chose a file", which is not an error;
 * 2. a POST larger than `post_max_size`, which PHP discards silently and which
 *    looks exactly like a form posted with no CSRF token;
 * 3. what a file actually is, which is decided by its bytes and never by what
 *    the browser called it.
 */
#[CoversClass(UploadedFile::class)]
#[CoversClass(FileTypes::class)]
final class UploadedFileTest extends TestCase
{
    public function testOneUploadPerField(): void
    {
        $files = UploadedFile::mapOf([
            'photo' => ['name' => 'kolo.jpg', 'tmp_name' => '/tmp/php1', 'size' => 2048, 'error' => UPLOAD_ERR_OK],
        ]);

        self::assertArrayHasKey('photo', $files);
        self::assertSame('kolo.jpg', $files['photo']->originalName);
        self::assertSame(2048, $files['photo']->bytes);
        self::assertTrue($files['photo']->isPresent());
    }

    public function testAFieldNobodyFilledInIsPresentButNotAnError(): void
    {
        // A form with a file field that was left alone posts this, and it
        // means "leave the stored one alone" on an edit -- not a failure.
        $files = UploadedFile::mapOf([
            'photo' => ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE],
        ]);

        self::assertFalse($files['photo']->isPresent());
        self::assertNull($files['photo']->failure());
    }

    public function testAMultipleUploadIsIgnoredRatherThanHalfRead(): void
    {
        // `name="photos[]"` arrives as parallel arrays. Nothing in this admin
        // declares one, and reading it halfway would be worse than not at all.
        $files = UploadedFile::mapOf([
            'photos' => ['name' => ['a.jpg', 'b.jpg'], 'tmp_name' => ['/tmp/1', '/tmp/2']],
        ]);

        self::assertSame([], $files);
    }

    public function testEveryFailureSaysSomethingAPersonCanActOn(): void
    {
        foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE] as $error) {
            self::assertSame('That file is too large to upload.', $this->failing($error)->failure());
        }

        self::assertStringContainsString('in part', (string) $this->failing(UPLOAD_ERR_PARTIAL)->failure());
        self::assertStringContainsString(
            'administers this installation',
            (string) $this->failing(UPLOAD_ERR_CANT_WRITE)->failure(),
        );
    }

    public function testAFailureNeverNamesTheServersOwnProblem(): void
    {
        // "UPLOAD_ERR_CANT_WRITE" tells an administrator nothing they can do
        // and tells an attacker which part of the server is broken.
        $message = (string) $this->failing(UPLOAD_ERR_NO_TMP_DIR)->failure();

        self::assertStringNotContainsString('tmp', strtolower($message));
        self::assertStringNotContainsString('UPLOAD_ERR', $message);
    }

    // --- the body PHP threw away ---

    public function testAnOversizedPostIsToldApartFromAMissingToken(): void
    {
        $request = new Request(
            'POST',
            'a/ads/update',
            headers: ['Content-Type' => 'multipart/form-data; boundary=----x'],
            contentLength: 50_000_000,
        );

        self::assertTrue($request->bodyWasDiscarded());
    }

    public function testAnOrdinaryPostIsNotMistakenForOne(): void
    {
        $withFields = new Request(
            'POST',
            'a/ads/update',
            body: ['title' => 'x'],
            headers: ['Content-Type' => 'multipart/form-data; boundary=----x'],
            contentLength: 500,
        );

        $plainForm = new Request(
            'POST',
            'a/ads/update',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            contentLength: 0,
        );

        self::assertFalse($withFields->bodyWasDiscarded());
        self::assertFalse($plainForm->bodyWasDiscarded());
        self::assertFalse((new Request('GET', 'p/ads'))->bodyWasDiscarded());
    }

    // --- what a file is ---

    /**
     * Real files, one pixel each, rather than a signature with noise behind
     * it.
     *
     * The first attempt at this test used eight bytes of PNG header and
     * sixty-four bytes of 'x', and `finfo` refused it -- correctly, which is
     * the whole reason it is asked. A test that had passed would have been a
     * test of the signature table alone.
     */
    public function testTheTypeComesFromTheBytes(): void
    {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

        self::assertSame('image/png', FileTypes::of(self::png(), $allowed));
        self::assertSame('image/gif', FileTypes::of(self::gif(), $allowed));
        self::assertSame('image/jpeg', FileTypes::of(self::jpeg(), $allowed));
        self::assertSame('application/pdf', FileTypes::of(self::pdf(), $allowed));
    }

    /**
     * A file whose first bytes say one thing and whose contents say another.
     *
     * Caught by `finfo` where it is loaded, and by nothing where it is not --
     * which is why the signature table is the floor and not the ceiling, and
     * why this assertion is conditional rather than quietly absent.
     */
    public function testBytesThatOnlyBeginLikeAPngAreCaughtWhereFinfoExists(): void
    {
        if (!class_exists(\finfo::class)) {
            self::markTestSkipped('ext-fileinfo is not loaded, so there is no second opinion to test.');
        }

        self::assertNull(FileTypes::of("\x89PNG\r\n\x1A\n" . str_repeat('x', 64), ['image/png']));
    }

    public function testAScriptNamedLikeAPhotographIsNotAPhotograph(): void
    {
        // The whole point. The browser would have called this image/jpeg
        // because the filename said so.
        self::assertNull(FileTypes::of('<?php system($_GET["c"]); ?>', ['image/jpeg']));
    }

    public function testATypeTheInstallationDoesNotAllowIsNotDetected(): void
    {
        $pdf = '%PDF-1.7' . str_repeat('x', 64);

        self::assertSame('application/pdf', FileTypes::of($pdf, ['application/pdf']));
        self::assertNull(FileTypes::of($pdf, ['image/jpeg', 'image/png']));
    }

    public function testAnEmptyFileIsNotAType(): void
    {
        self::assertNull(FileTypes::of('', ['image/jpeg']));
    }

    public function testTheExtensionComesFromTheTypeAndNotFromTheName(): void
    {
        self::assertSame('jpg', FileTypes::extensionOf('image/jpeg'));
        self::assertSame('pdf', FileTypes::extensionOf('application/pdf'));
    }

    public function testAKeyIsDatedRandomAndCarriesNothingOfTheUploadedName(): void
    {
        $key = FileTypes::keyFor('ads', 'image/jpeg');

        self::assertMatchesRegularExpression('~^ads/\d{4}/\d{2}/[a-f0-9]{32}\.jpg$~', $key);
        self::assertNotSame($key, FileTypes::keyFor('ads', 'image/jpeg'), 'Two keys collided.');
    }

    public function testAPrefixThatIsNotPlainIsMadeOne(): void
    {
        // A page name reaches this, and a page name is configuration.
        self::assertStringStartsWith('my-page/', FileTypes::keyFor('My Page!', 'image/png'));
        self::assertStringStartsWith('files/', FileTypes::keyFor('///', 'image/png'));
    }

    /** One transparent pixel, 67 bytes. */
    private static function png(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }

    /** One pixel, GIF89a. */
    private static function gif(): string
    {
        return (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);
    }

    /** One pixel, baseline JPEG. */
    private static function jpeg(): string
    {
        return (string) base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIs'
            . 'IxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAA'
            . 'AAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==',
            true,
        );
    }

    /** The smallest thing both the signature table and finfo call a PDF. */
    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    private function failing(int $error): UploadedFile
    {
        return new UploadedFile('kolo.jpg', '', 0, $error);
    }
}
