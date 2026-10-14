<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Config;
use RockAdmin\Config\Enums;
use RockAdmin\Mail\CallbackMailer;
use RockAdmin\Mail\LogMailer;
use RockAdmin\Mail\Mailers;
use RockAdmin\Mail\MailException;
use RockAdmin\Mail\Message;
use RockAdmin\Mail\SendmailMailer;
use RockAdmin\Mail\SmtpMailer;

/**
 * Which driver the configuration asked for.
 *
 * One place reads `mail.driver`, so this is where a host finds out that
 * changing `log` to `smtp` in production needs no other edit. The assertions
 * that matter are the refusals: every one of them is a misconfiguration that
 * would otherwise be discovered by somebody waiting for a password reset that
 * never came, and each says which key to fix.
 */
#[CoversClass(Mailers::class)]
final class MailersTest extends TestCase
{
    public function testTheDefaultDriverWritesFiles(): void
    {
        self::assertInstanceOf(LogMailer::class, Mailers::fromConfig($this->config([])));
    }

    public function testEachDriverNameBuildsItsOwn(): void
    {
        self::assertInstanceOf(LogMailer::class, Mailers::fromConfig($this->config(['driver' => 'log'])));
        self::assertInstanceOf(SendmailMailer::class, Mailers::fromConfig($this->config(['driver' => 'sendmail'])));
        self::assertInstanceOf(SmtpMailer::class, Mailers::fromConfig($this->config([
            'driver' => 'smtp',
            'host' => 'mail.example.com',
        ])));
        self::assertInstanceOf(CallbackMailer::class, Mailers::fromConfig(
            $this->config(['driver' => 'callback']),
            static function (Message $message): void {
            },
        ));
    }

    public function testADriverNobodyHasIsRefusedByName(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('log, smtp, sendmail, callback');

        Mailers::fromConfig($this->config(['driver' => 'mailgun']));
    }

    public function testTheCallbackDriverWithNoClosureSaysWhatIsMissing(): void
    {
        // The one misconfiguration a configuration file cannot show you: the
        // key says `callback` and the bootstrap passed nothing.
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('no closure was passed to the bootstrap');

        Mailers::fromConfig($this->config(['driver' => 'callback']));
    }

    public function testSmtpWithNoHostIsRefused(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('nothing to connect to');

        Mailers::fromConfig($this->config(['driver' => 'smtp']));
    }

    public function testAnEncryptionNobodyHasIsRefused(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('mail.encryption');

        Mailers::fromConfig($this->config([
            'driver' => 'smtp',
            'host' => 'mail.example.com',
            'encryption' => 'starttls-maybe',
        ]));
    }

    public function testTheSenderComesFromConfiguration(): void
    {
        $from = Mailers::from($this->config([
            'from' => ['address' => 'admin@example.com', 'name' => 'Cyklobazar admin'],
        ]));

        self::assertSame('admin@example.com', $from->address);
        self::assertSame('Cyklobazar admin <admin@example.com>', $from->header());

        // And a name that needs it is encoded on the way into the header,
        // which is the other half of what a sender has to survive.
        $czech = Mailers::from($this->config([
            'from' => ['address' => 'admin@example.com', 'name' => 'Cyklobazar správa'],
        ]));

        self::assertSame(
            '=?UTF-8?B?' . base64_encode('Cyklobazar správa') . '?= <admin@example.com>',
            $czech->header(),
        );
    }

    public function testNoSenderIsRefusedWithTheReason(): void
    {
        // An empty `from` is the misconfiguration whose symptom is mail that
        // is accepted and then filed as spam, which nobody traces back here.
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('mail.from.address is empty');

        Mailers::from($this->config([]));
    }

    /** @param array<string, mixed> $mail */
    private function config(array $mail): Config
    {
        return new Config(
            ['mail' => $mail, 'paths' => ['logs' => sys_get_temp_dir() . '/ra-mailers']],
            Enums::fromConfig([]),
        );
    }
}
