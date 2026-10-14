<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

/**
 * One message, in both halves.
 *
 * **Plain text is not optional and is not generated.** Specification 10 says
 * every message goes out as HTML and text, and the usual shortcut -- strip the
 * tags off the HTML -- produces the kind of plain text nobody reads: a reset
 * link somewhere under a flattened heading and a row of navigation. Two
 * templates cost one more file per message and are the difference between a
 * fallback and an afterthought.
 *
 * **`$kind` is the message's name, not its subject.** `password-reset` is what
 * the log driver puts in the filename and what a host's own mailer switches on
 * if it wants to route one kind differently. A subject is written for a person
 * and is translated, reworded and localised; a name is written for code.
 */
final class Message
{
    public function __construct(
        public readonly Mailbox $from,
        public readonly Mailbox $to,
        public readonly string $subject,
        /** The HTML half, already rendered and already escaped by its template. */
        public readonly string $html,
        /** The text half, rendered from its own template. */
        public readonly string $text,
        /** `password-reset`, `invitation`: which message this is, in lower-case kebab. */
        public readonly string $kind = 'message',
    ) {
        if (preg_match('/[\r\n]/', $subject) === 1) {
            // A subject is written into a header. A newline in one ends that
            // header and begins whatever the next line says -- which is how a
            // message acquires a recipient nobody wrote.
            throw new MailException(
                'A subject cannot contain a line break: it is written into a header, and a header '
                . 'that ends early is a header somebody else can continue.',
            );
        }
    }
}
