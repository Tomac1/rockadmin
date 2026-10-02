<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Mail;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\MailException;
use RockAdmin\Mail\Message;
use RockAdmin\Mail\Mime;

/**
 * What a message is, and what it turns into on the wire.
 *
 * Two kinds of assertion here and they are not the same kind. The MIME shape
 * is a correctness question -- a client that cannot find the HTML part shows
 * the text one, and one that can find neither shows nothing. The line breaks
 * and the refusals are a security question: a header that ends early is a
 * header somebody else can continue, and the value they continue it with is
 * `Bcc:`.
 */
#[CoversClass(Message::class)]
#[CoversClass(Mailbox::class)]
#[CoversClass(Mime::class)]
final class MessageTest extends TestCase
{
    public function testTheMessageCarriesBothHalvesInTheOrderAClientReadsThem(): void
    {
        $mime = $this->mime();
        $body = $mime->body();

        $text = strpos($body, 'text/plain');
        $html = strpos($body, 'text/html');

        self::assertIsInt($text);
        self::assertIsInt($html);
        self::assertLessThan(
            $html,
            $text,
            'The HTML part came first, so a client showing the last part it understands shows the text.',
        );
    }

    public function testBothPartsCarryTheirOwnBody(): void
    {
        $body = $this->mime()->body();

        self::assertStringContainsString(
            base64_encode('Odkaz: https://example.com/reset/abc'),
            str_replace("\r\n", '', $body),
        );
        self::assertStringContainsString(
            base64_encode('<p><a href="https://example.com/reset/abc">Obnovit heslo</a></p>'),
            str_replace("\r\n", '', $body),
        );
    }

    public function testTheBoundaryInTheHeaderIsTheOneInTheBody(): void
    {
        $mime = $this->mime();

        $matched = preg_match('/boundary="([^"]+)"/', $mime->headerBlock(), $found) === 1;

        self::assertTrue($matched, 'The Content-Type header declares no boundary.');
        self::assertStringContainsString(
            '--' . $found[1] . '--',
            $mime->body(),
            'The body ends with a different boundary than the header declared.',
        );
    }

    public function testEveryLineEndsWithCrlf(): void
    {
        // A lone LF in a header is how a message is truncated by one server
        // and rejected by the next.
        $full = $this->mime()->full();

        self::assertSame(0, preg_match('/(?<!\r)\n/', $full), 'A line ended with a bare LF.');
    }

    public function testACzechSubjectIsEncodedRatherThanSentAsIs(): void
    {
        $headers = $this->mime('Obnovení hesla')->headers();

        self::assertSame('=?UTF-8?B?' . base64_encode('Obnovení hesla') . '?=', $headers['Subject']);
    }

    public function testAnAsciiSubjectIsLeftReadable(): void
    {
        // Encoding everything would be simpler and would make every log file
        // and every raw view unreadable for no gain.
        self::assertSame('Password reset', $this->mime('Password reset')->headers()['Subject']);
    }

    public function testANameIsEncodedButTheAddressIsNot(): void
    {
        $mailbox = new Mailbox('jana@example.com', 'Jana Nováková');

        self::assertSame('=?UTF-8?B?' . base64_encode('Jana Nováková') . '?= <jana@example.com>', $mailbox->header());
        self::assertSame('<jana@example.com>', $mailbox->envelope());
    }

    public function testAMailboxWithNoNameIsJustTheAddress(): void
    {
        self::assertSame('jana@example.com', (new Mailbox('jana@example.com'))->header());
    }

    public function testAnAddressCarryingALineBreakIsRefused(): void
    {
        $this->expectException(MailException::class);

        new Mailbox("jana@example.com\r\nBcc: everyone@example.com");
    }

    public function testSomethingThatIsNotAnAddressIsRefused(): void
    {
        $this->expectException(MailException::class);

        new Mailbox('jana');
    }

    public function testASubjectCarryingALineBreakIsRefused(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('line break');

        new Message(
            new Mailbox('admin@example.com'),
            new Mailbox('jana@example.com'),
            "Password reset\r\nBcc: everyone@example.com",
            '<p>x</p>',
            'x',
        );
    }

    public function testHeadersATransportWritesItselfCanBeLeftOut(): void
    {
        // `mail()` is handed the recipient and the subject as arguments and
        // writes both headers itself; passing them again produces a message
        // with two Subject lines.
        $block = $this->mime()->headerBlock(['to', 'subject']);

        self::assertStringNotContainsString('To:', $block);
        self::assertStringNotContainsString('Subject:', $block);
        self::assertStringContainsString('From:', $block);
    }

    public function testTheMessageIdIsInTheSendersDomain(): void
    {
        self::assertStringEndsWith('@example.com>', $this->mime()->headers()['Message-ID']);
    }

    private function mime(string $subject = 'Obnovení hesla'): Mime
    {
        return new Mime(
            new Message(
                new Mailbox('admin@example.com', 'Cyklobazar admin'),
                new Mailbox('jana@example.com', 'Jana Nováková'),
                $subject,
                '<p><a href="https://example.com/reset/abc">Obnovit heslo</a></p>',
                'Odkaz: https://example.com/reset/abc',
                'password-reset',
            ),
            new DateTimeImmutable('2026-10-03 14:30:12'),
        );
    }
}
