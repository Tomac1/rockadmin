<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

/**
 * How this admin sends a message.
 *
 * A seam before it is a feature, and rule 2 lists it beside `SessionStore` and
 * `Authenticator` for the reason they are all there: a project running Laravel
 * already has a mailer, its templates, its queue, its retries and its
 * deliverability, and an admin that opened its own socket would be doing all
 * of that again, worse, from inside somebody else's application.
 *
 * So the implementations that ship are a convenience for a project that has
 * none of that, and `CallbackMailer` -- three lines around a closure -- is the
 * one a real project is expected to use.
 *
 * **A failure raises.** There is no boolean to ignore and no queue to lose it
 * in. Both messages this SDK sends are a person waiting for one.
 */
interface Mailer
{
    /** @throws MailException when the message did not go out */
    public function send(Message $message): void;
}
