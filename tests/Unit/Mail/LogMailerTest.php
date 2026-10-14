<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Mail\LogMailer;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\MailException;
use RockAdmin\Mail\Message;

/**
 * The default driver, which is a development tool rather than a placeholder.
 *
 * The point of writing a real `.eml` is that the person who has just built a
 * password reset can open the file, see the message a user would have seen and
 * click the link in it. So the assertions are about the file being a message:
 * named for who it went to, openable, and complete.
 */
#[CoversClass(LogMailer::class)]
final class LogMailerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-mail-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);

        parent::tearDown();
    }

    public function testItWritesOneFileNamedForTheMessageAndTheRecipient(): void
    {
        (new LogMailer($this->root))->send($this->message());

        $files = $this->written();

        self::assertCount(1, $files);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}_\d{6}_password-reset_jana@example\.com\.eml$/',
            basename($files[0]),
        );
    }

    public function testTheFileIsTheMessageItWouldHaveSent(): void
    {
        (new LogMailer($this->root))->send($this->message());

        $eml = (string) file_get_contents($this->written()[0]);

        self::assertStringContainsString('From: admin@example.com', $eml);
        self::assertStringContainsString('To: jana@example.com', $eml);
        self::assertStringContainsString('Content-Type: multipart/alternative;', $eml);
        self::assertStringContainsString(
            base64_encode('https://example.com/reset/abc'),
            str_replace("\r\n", '', $eml),
            'The link is not in the file, so the file is of no use to anybody.',
        );
    }

    public function testTheDirectoryIsCreatedUnderTheConfiguredLogPath(): void
    {
        self::assertDirectoryDoesNotExist($this->root);

        (new LogMailer($this->root))->send($this->message());

        self::assertDirectoryExists($this->root . '/mail');
    }

    public function testTwoMessagesAreTwoFiles(): void
    {
        $mailer = new LogMailer($this->root);

        $mailer->send($this->message());
        $mailer->send($this->message('petr@example.com'));

        self::assertCount(2, $this->written());
    }

    /**
     * An address is user input and it reaches a filesystem path. Everything
     * that is not plainly safe becomes a dash, so there is no traversal to
     * attempt and no separator to smuggle.
     */
    public function testAnAddressCannotSteerTheFileOutOfTheDirectory(): void
    {
        $mailer = new LogMailer($this->root);

        $mailer->send(new Message(
            new Mailbox('admin@example.com'),
            // Legal enough for the address check -- no spaces, no brackets --
            // and nothing like a path by the time it is a filename.
            new Mailbox('..~..~..~etc~passwd@example.com'),
            'Password reset',
            '<p>x</p>',
            'x',
        ));

        $files = $this->written();

        self::assertCount(1, $files);
        self::assertStringContainsString('/mail/', str_replace('\\', '/', $files[0]));
        self::assertStringNotContainsString('..', basename($files[0]));
    }

    public function testADirectoryThatCannotBeCreatedSaysSoRatherThanFailingSilently(): void
    {
        // A file where the directory should be: `mkdir()` cannot win, and the
        // message has to name the path rather than say "false".
        $path = $this->root . '/not-a-directory';
        mkdir($this->root, 0o775, true);
        file_put_contents($path, 'x');

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('mail log directory');

        (new LogMailer($path))->send($this->message());
    }

    private function message(string $to = 'jana@example.com'): Message
    {
        return new Message(
            new Mailbox('admin@example.com'),
            new Mailbox($to),
            'Password reset',
            '<p><a href="https://example.com/reset/abc">Reset</a></p>',
            'https://example.com/reset/abc',
            'password-reset',
        );
    }

    /** @return list<string> */
    private function written(): array
    {
        return glob($this->root . '/mail/*.eml') ?: [];
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
