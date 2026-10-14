<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

use RockAdmin\View\MailView;
use RockAdmin\View\Renderer;

/**
 * The two messages this admin sends, built from templates.
 *
 * It is a factory rather than two methods on a handler because both halves of
 * both messages go through the same four steps -- render the content, wrap it
 * in the layout, do it again for the text, assemble a `Message` -- and a second
 * copy of those steps is where one message quietly loses its text half.
 *
 * **Templates, through the ordinary cascade.** `templates/mail/` is searched
 * the same way `templates/region/` is, so a project changes the wording of a
 * password reset by writing `mail/password-reset.php` in its own template
 * directory. Nothing is registered and nothing is copied -- the same rule as
 * everywhere else in the view layer.
 *
 * **The URL is absolute.** A mail client has no base and no session; a link
 * beginning with `/admin` arrives as a link to nothing. Whoever calls this
 * builds the URL with `UrlGenerator` and makes it absolute, because only the
 * host knows what it is reachable as from outside.
 */
final class MessageFactory
{
    private const string RESET_SUBJECT = 'Set a new password';

    private const string INVITATION_SUBJECT = 'Your administrator account';

    public function __construct(
        private readonly Renderer $renderer,
        /** Who the mail is from, out of `mail.from`. */
        private readonly Mailbox $from,
        /** The admin's own name, as the message signs itself. */
        private readonly string $brand,
    ) {
    }

    /** The message somebody gets because they asked to set a new password. */
    public function passwordReset(Mailbox $to, string $url, int $lifetimeMinutes): Message
    {
        return $this->build('password-reset', self::RESET_SUBJECT, $to, $url, self::inWords($lifetimeMinutes));
    }

    /** The message somebody gets because an account was made for them. */
    public function invitation(Mailbox $to, string $url, int $lifetimeMinutes): Message
    {
        return $this->build('invitation', self::INVITATION_SUBJECT, $to, $url, self::inWords($lifetimeMinutes));
    }

    private function build(string $kind, string $subject, Mailbox $to, string $url, string $expiry): Message
    {
        $view = new MailView(
            brand: $this->brand,
            title: $subject,
            // By name where there is one. "Hello jana@example.com" is what a
            // system that does not know who you are says, and this one does.
            recipient: $to->name === '' ? $to->address : $to->name,
            url: $url,
            expiry: $expiry,
        );

        return new Message(
            from: $this->from,
            to: $to,
            subject: $subject,
            html: $this->renderer->render('mail/layout', $view->withBody(
                $this->renderer->render("mail/{$kind}", $view),
            )),
            text: trim($this->renderer->render('mail/layout.txt', $view->withBody(
                trim($this->renderer->render("mail/{$kind}.txt", $view)),
            ))) . "\n",
            kind: $kind,
        );
    }

    /**
     * A lifetime a person can act on.
     *
     * "Expires in 2880 minutes" is a number nobody converts in their head, and
     * an invitation that says so reads as an administrative detail rather than
     * as a deadline. Hours and days where they divide evenly, minutes
     * otherwise -- never a rounded "about 2 days", because the one person who
     * checks will be the one whose link has just expired.
     */
    private static function inWords(int $minutes): string
    {
        if ($minutes % 1440 === 0 && $minutes >= 1440) {
            $days = intdiv($minutes, 1440);

            return $days === 1 ? '1 day' : "{$days} days";
        }

        if ($minutes % 60 === 0 && $minutes >= 60) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
    }
}
