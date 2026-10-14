<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\MessageFactory;
use RockAdmin\View\Escaper;
use RockAdmin\View\MailView;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The two messages, in both halves.
 *
 * What is actually being checked is that neither half can quietly go missing
 * or go out empty: the link has to be in both, because the half somebody reads
 * is decided by their mail client and not by us, and the text half is the one
 * that silently rots -- nobody looks at it, so nothing but a test notices when
 * it stops carrying the link.
 */
#[CoversClass(MessageFactory::class)]
#[CoversClass(MailView::class)]
final class MessageFactoryTest extends TestCase
{
    public function testTheResetCarriesTheLinkInBothHalves(): void
    {
        $message = $this->factory()->passwordReset(
            new Mailbox('jana@example.com', 'Jana'),
            'https://admin.example.com/password/reset/abc123',
            60,
        );

        self::assertStringContainsString('https://admin.example.com/password/reset/abc123', $message->html);
        self::assertStringContainsString('https://admin.example.com/password/reset/abc123', $message->text);
    }

    public function testTheHtmlHalfIsADocumentAndTheTextHalfIsNot(): void
    {
        $message = $this->factory()->passwordReset(new Mailbox('jana@example.com'), 'https://x.test/r/1', 60);

        self::assertStringStartsWith('<!doctype html>', ltrim($message->html));
        self::assertStringNotContainsString('<', $message->text, 'The text half carries markup.');
    }

    public function testBothHalvesSayHowLongTheLinkLasts(): void
    {
        $message = $this->factory()->passwordReset(new Mailbox('jana@example.com'), 'https://x.test/r/1', 60);

        self::assertStringContainsString('1 hour', $message->html);
        self::assertStringContainsString('1 hour', $message->text);
    }

    public function testAnInvitationsLifetimeIsSaidInDaysRatherThanInMinutes(): void
    {
        // "Expires in 2880 minutes" is a number nobody converts in their head.
        $message = $this->factory()->invitation(new Mailbox('jana@example.com'), 'https://x.test/r/1', 2880);

        self::assertStringContainsString('2 days', $message->text);
        self::assertStringNotContainsString('2880', $message->text);
    }

    /**
     * PHP eats exactly one newline after a closing tag, which in a text
     * template means a blank line that was written is not a blank line that
     * arrives -- and a paragraph break that quietly disappears turns the text
     * half into a wall. It happened twice while these templates were written,
     * once welding a word onto the brand name, so it is pinned here rather
     * than left to whoever next edits them.
     */
    public function testTheTextHalfKeepsTheBlankLinesItWasWrittenWith(): void
    {
        $text = $this->factory()->passwordReset(
            new Mailbox('jana@example.com', 'Jana'),
            'https://x.test/r/1',
            60,
        )->text;

        self::assertStringContainsString("Hello Jana,\n\n", $text);
        self::assertStringContainsString("\n\nhttps://x.test/r/1\n\n", $text, 'The link does not stand alone.');

        $invitation = $this->factory()->invitation(new Mailbox('jana@example.com'), 'https://x.test/r/1', 2880)->text;

        self::assertStringContainsString('Cyklobazar admin about', $invitation, 'A word was welded onto the brand.');
    }

    public function testSomebodyWithANameIsGreetedByIt(): void
    {
        $named = $this->factory()->passwordReset(new Mailbox('jana@example.com', 'Jana'), 'https://x.test/r/1', 60);
        $anonymous = $this->factory()->passwordReset(new Mailbox('jana@example.com'), 'https://x.test/r/1', 60);

        self::assertStringContainsString('Hello Jana,', $named->text);
        self::assertStringContainsString('Hello jana@example.com,', $anonymous->text);
    }

    public function testBothMessagesCarryTheBrandAndTheSender(): void
    {
        $message = $this->factory()->invitation(new Mailbox('jana@example.com'), 'https://x.test/r/1', 2880);

        self::assertStringContainsString('Cyklobazar admin', $message->text);
        self::assertStringContainsString('Cyklobazar admin', $message->html);
        self::assertSame('admin@example.com', $message->from->address);
    }

    public function testTheTwoMessagesAreToldApartByKind(): void
    {
        // Which is what the log driver puts in the filename, and what a host's
        // own mailer switches on if it routes one kind differently.
        self::assertSame(
            'password-reset',
            $this->factory()->passwordReset(new Mailbox('j@example.com'), 'https://x.test/1', 60)->kind,
        );
        self::assertSame(
            'invitation',
            $this->factory()->invitation(new Mailbox('j@example.com'), 'https://x.test/1', 60)->kind,
        );
    }

    /**
     * A name is data: it comes out of `ra_users` and whoever created the
     * account typed it.
     */
    public function testANameIsEscapedInTheHtmlHalf(): void
    {
        $message = $this->factory()->passwordReset(
            new Mailbox('jana@example.com', 'Jana <script>alert(1)</script>'),
            'https://x.test/r/1',
            60,
        );

        self::assertStringNotContainsString('<script>', $message->html);
        self::assertStringContainsString('&lt;script&gt;', $message->html);
    }

    public function testAProjectCanReplaceAMailTemplateTheWayItReplacesAnyOther(): void
    {
        $root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-mailtpl-' . bin2hex(random_bytes(6));
        mkdir($root . '/mail', 0o777, true);
        file_put_contents($root . '/mail/password-reset.txt.php', 'Ahoj, nove heslo: <?= $view->url ?>');

        try {
            $message = $this->factory([$root])->passwordReset(
                new Mailbox('jana@example.com'),
                'https://x.test/r/1',
                60,
            );

            self::assertStringContainsString('Ahoj, nove heslo: https://x.test/r/1', $message->text);
            self::assertStringContainsString('Set a new password', $message->html, 'The HTML half changed too.');
        } finally {
            unlink($root . '/mail/password-reset.txt.php');
            rmdir($root . '/mail');
            rmdir($root);
        }
    }

    /** @param list<string> $paths */
    private function factory(array $paths = []): MessageFactory
    {
        return new MessageFactory(
            new Renderer(new TemplateResolver($paths), new Escaper(), new UrlGenerator('/admin')),
            new Mailbox('admin@example.com', 'Cyklobazar admin'),
            'Cyklobazar admin',
        );
    }
}
