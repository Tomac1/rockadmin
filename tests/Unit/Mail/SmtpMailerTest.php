<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\MailException;
use RockAdmin\Mail\Message;
use RockAdmin\Mail\SmtpMailer;
use RockAdmin\Mail\SmtpTransport;

/**
 * The SMTP conversation, against a server that says what the test tells it to.
 *
 * This is why `SmtpTransport` is an interface. The parts of SMTP that go wrong
 * are the ones only a server provokes -- a multi-line greeting, a refusal
 * halfway through, an AUTH accepted at the username and refused at the
 * password -- and a scripted fake provokes all of them in a millisecond. The
 * alternative is finding out on the day somebody's password reset does not
 * arrive.
 *
 * Every refusal has a test, because SMTP will happily carry on with a
 * conversation that has already gone wrong: a server that refuses the
 * recipient and is not noticed goes on to accept DATA, accept the message, and
 * deliver it to nobody.
 */
#[CoversClass(SmtpMailer::class)]
final class SmtpMailerTest extends TestCase
{
    public function testTheHappyPathSaysEverythingInOrder(): void
    {
        $server = $this->server([
            '220 mail.example.com ESMTP',
            '250-mail.example.com',
            '250 STARTTLS',
            '220 go ahead',
            '250-mail.example.com',
            '250 AUTH LOGIN PLAIN',
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '235 authenticated',
            '250 sender ok',
            '250 recipient ok',
            '354 go ahead',
            '250 queued as 12345',
            '221 bye',
        ]);

        $this->mailer($server)->send($this->message());

        self::assertSame([
            'EHLO admin.example.com',
            'STARTTLS',
            '[tls]',
            'EHLO admin.example.com',
            'AUTH LOGIN',
            base64_encode('postmaster'),
            base64_encode('hunter2'),
            'MAIL FROM:<admin@example.com>',
            'RCPT TO:<jana@example.com>',
            'DATA',
            '[message]',
            'QUIT',
            '[closed]',
        ], $server->said());
    }

    public function testTheMessageItselfIsSentAndEndsWithALoneDot(): void
    {
        $server = $this->server($this->conversation());
        $this->mailer($server)->send($this->message());

        $data = $server->data();

        self::assertStringContainsString('Subject: Set a new password', $data);
        self::assertStringEndsWith("\r\n.\r\n", $data, 'The message does not end, so the server waits forever.');
    }

    public function testAMultiLineReplyIsReadWhole(): void
    {
        // The classic bug: read one line of a multi-line reply and the rest
        // are read as the answer to the next command, so every reply after it
        // is off by one and the error names the wrong step.
        $server = $this->server([
            '220 mail.example.com ESMTP',
            '250-mail.example.com',
            '250-PIPELINING',
            '250-SIZE 10240000',
            '250 STARTTLS',
            '220 go ahead',
            '250-mail.example.com',
            '250 AUTH LOGIN',
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '235 authenticated',
            '250 sender ok',
            '250 recipient ok',
            '354 go ahead',
            '250 queued',
            '221 bye',
        ]);

        $this->mailer($server)->send($this->message());

        self::assertContains('QUIT', $server->said(), 'The conversation did not reach the end.');
    }

    public function testAServerThatDoesNotOfferStartTlsIsRefusedRatherThanSentToInTheClear(): void
    {
        $server = $this->server(['220 mail.example.com ESMTP', '250 mail.example.com']);

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('STARTTLS');

        $this->mailer($server)->send($this->message());
    }

    public function testCredentialsRefusedSayWhatStepFailedAndNotWhatWasSent(): void
    {
        $server = $this->server([
            '220 mail.example.com ESMTP',
            '250-mail.example.com',
            '250 STARTTLS',
            '220 go ahead',
            '250-mail.example.com',
            '250 AUTH LOGIN',
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '535 authentication failed',
        ]);

        try {
            $this->mailer($server)->send($this->message());

            self::fail('A refused password was treated as a sent message.');
        } catch (MailException $e) {
            self::assertStringContainsString('AUTH', $e->getMessage());
            self::assertStringNotContainsString('hunter2', $e->getMessage(), 'The password reached an exception.');
            self::assertStringNotContainsString(base64_encode('hunter2'), $e->getMessage());
        }
    }

    public function testARefusedRecipientStopsTheConversation(): void
    {
        $conversation = $this->conversation();
        $conversation[10] = '550 no such mailbox';
        $server = $this->server(array_values($conversation));

        try {
            $this->mailer($server)->send($this->message());

            self::fail('A refused recipient was treated as a sent message.');
        } catch (MailException $e) {
            self::assertStringContainsString('recipient', $e->getMessage());
            self::assertStringContainsString('550 no such mailbox', $e->getMessage(), "The server's words are gone.");
        }

        self::assertNotContains('DATA', $server->said(), 'It carried on after the recipient was refused.');
        self::assertContains('[closed]', $server->said(), 'A failed conversation was left open.');
    }

    public function testAServerThatRefusesTheMessageAfterDataIsNoticed(): void
    {
        $conversation = $this->conversation();
        $conversation[12] = '552 message too large';

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('552 message too large');

        $this->mailer($this->server(array_values($conversation)))->send($this->message());
    }

    public function testWithoutCredentialsItDoesNotAuthenticate(): void
    {
        $server = $this->server([
            '220 mail.example.com ESMTP',
            '250-mail.example.com',
            '250 STARTTLS',
            '220 go ahead',
            '250 mail.example.com',
            '250 sender ok',
            '250 recipient ok',
            '354 go ahead',
            '250 queued',
            '221 bye',
        ]);

        (new SmtpMailer(
            static fn (): SmtpTransport => $server,
            clientName: 'admin.example.com',
        ))->send($this->message());

        self::assertNotContains('AUTH LOGIN', $server->said());
    }

    public function testWithEncryptionOffItNeitherAsksForNorStartsTls(): void
    {
        $server = $this->server([
            '220 mail.example.com ESMTP',
            '250 mail.example.com',
            '250 sender ok',
            '250 recipient ok',
            '354 go ahead',
            '250 queued',
            '221 bye',
        ]);

        (new SmtpMailer(
            static fn (): SmtpTransport => $server,
            encryption: 'none',
            clientName: 'admin.example.com',
        ))->send($this->message());

        self::assertNotContains('STARTTLS', $server->said());
        self::assertNotContains('[tls]', $server->said());
    }

    /** @return list<string> the whole happy-path conversation, for tests that break one line of it */
    private function conversation(): array
    {
        return [
            '220 mail.example.com ESMTP',
            '250-mail.example.com',
            '250 STARTTLS',
            '220 go ahead',
            '250-mail.example.com',
            '250 AUTH LOGIN',
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '235 authenticated',
            '250 sender ok',
            '250 recipient ok',
            '354 go ahead',
            '250 queued',
            '221 bye',
        ];
    }

    /** @param list<string> $replies */
    private function server(array $replies): ScriptedSmtpServer
    {
        return new ScriptedSmtpServer($replies);
    }

    private function mailer(ScriptedSmtpServer $server): SmtpMailer
    {
        return new SmtpMailer(
            static fn (): SmtpTransport => $server,
            username: 'postmaster',
            password: 'hunter2',
            clientName: 'admin.example.com',
        );
    }

    private function message(): Message
    {
        return new Message(
            new Mailbox('admin@example.com'),
            new Mailbox('jana@example.com'),
            'Set a new password',
            '<p>link</p>',
            'link',
            'password-reset',
        );
    }
}

/**
 * A server that answers from a list and writes down what it was told.
 *
 * It records commands rather than bytes: the message body is one entry
 * (`[message]`), because asserting on three kilobytes of base64 would be
 * asserting on `Mime`'s output in a test about SMTP. The body itself is kept
 * for the one test that is about it.
 */
final class ScriptedSmtpServer implements SmtpTransport
{
    /** @var list<string> */
    private array $said = [];

    private string $data = '';

    private int $next = 0;

    /** @param list<string> $replies */
    public function __construct(private readonly array $replies)
    {
    }

    public function open(): void
    {
    }

    public function readLine(): string
    {
        return $this->replies[$this->next++]
            ?? throw new MailException('The client said more than this server was scripted to answer.');
    }

    public function write(string $data): void
    {
        if (str_starts_with($data, 'Date:') || str_contains($data, 'MIME-Version:')) {
            $this->said[] = '[message]';
            $this->data = $data;

            return;
        }

        $this->said[] = rtrim($data, "\r\n");
    }

    public function startTls(): void
    {
        $this->said[] = '[tls]';
    }

    public function close(): void
    {
        $this->said[] = '[closed]';
    }

    /** @return list<string> */
    public function said(): array
    {
        return $this->said;
    }

    public function data(): string
    {
        return $this->data;
    }
}
