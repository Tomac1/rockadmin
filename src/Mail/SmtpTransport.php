<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

/**
 * The bytes under an SMTP conversation.
 *
 * It exists so that `SmtpMailer` can be tested. Two hundred lines of protocol
 * with no test is two hundred lines nobody dares change -- and the parts that
 * go wrong are the ones only a server can provoke: a multi-line greeting, a
 * server that refuses STARTTLS, an AUTH that fails after the username was
 * accepted, a recipient refused after the sender was taken. A scripted fake
 * provokes all of them in a unit test; a real server provokes them on the day
 * somebody's password reset does not arrive.
 *
 * The split is at the obvious seam: this moves bytes, `SmtpMailer` knows what
 * they mean. Reply codes, continuation lines and dot-stuffing are the
 * protocol's business and live in the mailer, so a second transport -- a test
 * double, a different socket library -- has nothing to reimplement.
 */
interface SmtpTransport
{
    /**
     * Connects. The greeting is not read here: a greeting is a reply, and
     * replies are read the same way wherever they appear.
     *
     * @throws MailException when the connection cannot be made
     */
    public function open(): void;

    /**
     * One line, without its trailing CRLF.
     *
     * @throws MailException on a timeout or a closed connection, which are
     *                       the same thing from here: no reply is coming
     */
    public function readLine(): string;

    /** @throws MailException when the line cannot be written */
    public function write(string $data): void;

    /**
     * Turns the open connection into an encrypted one, after STARTTLS was
     * accepted.
     *
     * @throws MailException when the handshake fails -- which is never
     *                       something to continue past, because continuing
     *                       means sending the password in the clear
     */
    public function startTls(): void;

    /** Closes it. Safe to call on a connection that was never opened. */
    public function close(): void;
}
