<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\StoredFileView;
use RockAdmin\Form\Submission;
use RockAdmin\Form\UploadLimits;
use RockAdmin\Http\UploadedFile;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;

/**
 * What a `file` field accepts, and what it says when it does not.
 *
 * The limits live in two places — `uploads.*` for the installation and
 * `accept`/`max_bytes` on the field — and the rule between them is the point
 * of most of this: **a field may narrow and may not widen.** A page file is
 * configuration a project writes, and if it could raise the ceiling the
 * installation-wide setting would be advice rather than a limit.
 *
 * The other half is what the refusals say. Somebody who has just been told no
 * needs to know what to upload instead, which is why the messages talk about
 * megabytes and JPEGs rather than about `uploads.max_bytes` and `image/jpeg`.
 */
#[CoversClass(FieldValidator::class)]
#[CoversClass(UploadLimits::class)]
#[CoversClass(StoredFileView::class)]
final class FileFieldTest extends TestCase
{
    public function testAnUploadNobodyMadeIsNotAFailureHere(): void
    {
        // An edit that touches nothing else must not be refused because the
        // file input was left alone: an empty one means "keep what is
        // stored". Whether anything *is* stored is the write path's business.
        self::assertSame([], $this->errorsFor(null));
        self::assertSame([], $this->errorsFor(null, required: true));
    }

    public function testAFileLargerThanTheFieldAllowsIsRefusedInMegabytes(): void
    {
        $errors = $this->errorsFor($this->upload(bytes: 2_000_000), maxBytes: 1_048_576);

        self::assertCount(1, $errors);
        self::assertStringContainsString('too large', $errors[0]);
        self::assertStringContainsString('1 MB', $errors[0]);
        self::assertStringNotContainsString('max_bytes', $errors[0], 'That is a key, not a sentence.');
    }

    public function testAFieldMayNarrowTheInstallationsLimitAndNotWidenIt(): void
    {
        $tooBigForTheField = $this->upload(bytes: 900_000);

        self::assertNotSame([], $this->errorsFor($tooBigForTheField, maxBytes: 500_000));
        self::assertSame([], $this->errorsFor($tooBigForTheField, maxBytes: 5_000_000, installationMax: 1_000_000));

        // The field asks for ten megabytes; the installation allows one.
        $overBoth = $this->upload(bytes: 2_000_000);

        self::assertNotSame(
            [],
            $this->errorsFor($overBoth, maxBytes: 10_000_000, installationMax: 1_000_000),
            'A page file raised the installation-wide limit, which makes it advice.',
        );
    }

    public function testWhatCountsIsWhatTheFileIsRatherThanWhatItIsCalled(): void
    {
        $errors = $this->errorsFor($this->upload(contents: '<?php system($_GET["c"]); ?>', name: 'kolo.jpg'));

        self::assertCount(1, $errors);
        self::assertStringContainsString('not a kind of file this page takes', $errors[0]);
        self::assertStringContainsString('what the file is, not what it is called', $errors[0]);
    }

    public function testTheRefusalNamesWhatToUploadInstead(): void
    {
        $errors = $this->errorsFor(
            $this->upload(contents: self::pdf()),
            accept: ['image/jpeg', 'image/png'],
        );

        self::assertCount(1, $errors);
        self::assertStringContainsString('JPEG or PNG', $errors[0]);
    }

    public function testAFieldCannotAcceptATypeTheInstallationDoesNot(): void
    {
        // `accept` is an intersection, not a replacement: a page asking for
        // PDFs on an installation that allows only images gets no PDFs.
        $errors = $this->errorsFor(
            $this->upload(contents: self::pdf()),
            accept: ['application/pdf'],
            installationTypes: ['image/jpeg', 'image/png'],
        );

        self::assertNotSame([], $errors);
    }

    public function testAnAcceptedFilePassesQuietly(): void
    {
        self::assertSame([], $this->errorsFor($this->upload(contents: self::png()), accept: ['image/png']));
    }

    public function testAnUploadThatFailedOnTheWayInSaysWhy(): void
    {
        $tooBigForPhp = new UploadedFile('kolo.jpg', '', 0, UPLOAD_ERR_INI_SIZE);

        self::assertSame(['That file is too large to upload.'], $this->errorsFor($tooBigForPhp));
    }

    public function testAFileFieldContributesNoValueToTheWrite(): void
    {
        // What reaches the column is a storage key the write path decides on;
        // the validator produces nothing for this field, rather than an empty
        // string that would clear it.
        $result = (new FieldValidator())->validate(
            $this->form(),
            Submission::fromBody([], $this->form(), ['photo' => $this->upload(contents: self::png())]),
        );

        self::assertSame([], $result->errors());
        self::assertArrayNotHasKey('photo', $result->values());
    }

    /**
     * The injection a security review found in the commit that added this
     * field, and that the test above was too polite to catch: it posted an
     * empty body.
     *
     * A `file` field's name in the body is a *string*, and nothing uploaded.
     * Until this was fixed it went straight into the column -- so a tampered
     * form pointed a row at any file in the bucket, another page's or another
     * tenant's, with no upload and no permission anywhere in the way.
     */
    public function testAStorageKeyPostedAsAStringIsNotAValue(): void
    {
        $form = $this->form();
        $submission = Submission::fromBody(
            ['photo' => 'ads/2026/10/somebody-elses-invoice.pdf'],
            $form,
        );

        self::assertNull($submission->value('photo'), 'The submission kept a posted storage key.');

        $result = (new FieldValidator())->validate($form, $submission);

        self::assertArrayNotHasKey(
            'photo',
            $result->values(),
            'A posted string reached the column, which is a storage key written by hand.',
        );
    }

    public function testAPostedStringDoesNotOverrideAGenuineUpload(): void
    {
        $form = $this->form();
        $result = (new FieldValidator())->validate($form, Submission::fromBody(
            ['photo' => 'ads/2026/10/somebody-elses-invoice.pdf'],
            $form,
            ['photo' => $this->upload(contents: self::png())],
        ));

        self::assertSame([], $result->errors());
        self::assertArrayNotHasKey('photo', $result->values());
    }

    // --- what the control is handed ---

    public function testAStoredImageIsSomethingToDrawAndAPdfIsSomethingToLinkTo(): void
    {
        $image = StoredFileView::of('ads/2026/10/8f3a.jpg', 'https://example.test/f/8f3a.jpg');
        $document = StoredFileView::of('ads/2026/10/8f3a.pdf', 'https://example.test/f/8f3a.pdf');

        self::assertTrue($image->isImage);
        self::assertFalse($document->isImage);
        self::assertSame('8f3a.jpg', $image->name, 'A person tells two files apart by the last segment.');
    }

    /**
     * @param list<string> $accept
     * @param list<string> $installationTypes
     * @return list<string>
     */
    private function errorsFor(
        ?UploadedFile $file,
        bool $required = false,
        ?int $maxBytes = null,
        array $accept = [],
        int $installationMax = 5_242_880,
        array $installationTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'],
    ): array {
        $form = $this->form($required, $maxBytes, $accept);
        $validator = new FieldValidator(new UploadLimits($installationMax, $installationTypes));

        $result = $validator->validate(
            $form,
            Submission::fromBody([], $form, $file === null ? [] : ['photo' => $file]),
        );

        return array_map(static fn ($error): string => $error->message, $result->errors());
    }

    /** @param list<string> $accept */
    private function form(bool $required = false, ?int $maxBytes = null, array $accept = []): FormDefinition
    {
        return new FormDefinition([
            'photo' => new FieldDefinition(
                key: 'photo',
                label: 'Photo',
                type: FieldType::File,
                default: null,
                required: $required,
                readonly: false,
                hidden: false,
                help: '',
                placeholder: '',
                accept: $accept,
                maxBytes: $maxBytes,
            ),
        ]);
    }

    private function upload(string $contents = '', int $bytes = 0, string $name = 'kolo.jpg'): UploadedFile
    {
        $contents = $contents === '' ? self::png() : $contents;
        $path = tempnam(sys_get_temp_dir(), 'ra-upload-');

        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($name, $path, $bytes > 0 ? $bytes : \strlen($contents));
    }

    private static function png(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }

    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }
}
