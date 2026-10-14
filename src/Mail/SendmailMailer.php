<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

/**
 * `mail()`, for hosting where that is what there is.
 *
 * It exists because a great deal of shared hosting has a working local MTA and
 * nothing else -- no SMTP credentials to put in a configuration file and no
 * framework mailer to borrow. On such a host this is the driver that works,
 * and on every other kind of host it is the one to avoid: `mail()` reports
 * only that the message was handed over, so a bounce, a refusal or a message
 * filed as spam all look exactly like success from here.
 *
 * **The recipient and the subject are arguments, and therefore not headers.**
 * `mail()` writes both itself, and passing them again produces a message with
 * two `Subject:` lines -- which some clients show and some ignore, and which
 * nobody notices until a user forwards a screenshot.
 */
final class SendmailMailer implements Mailer
{
    public function send(Message $message): void
    {
        $sent = @mail(
            $message->to->address,
            Mime::encodeWord($message->subject),
            ($mime = new Mime($message))->body(),
            $mime->headerBlock(['to', 'subject']),
        );

        if (!$sent) {
            throw new MailException(\sprintf(
                "The local mail system refused '%s' for %s. mail() does not say why; the server's own "
                . 'mail log does.',
                $message->kind,
                $message->to->address,
            ));
        }
    }
}
