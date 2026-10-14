<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

use Closure;

/**
 * An SMTP client, in about as many lines as it takes.
 *
 * It exists so that rule 1 can hold: the alternative to this file is a
 * dependency on PHPMailer or Symfony Mailer, and with it their release cycle,
 * their deprecations and their transitive packages -- inside an SDK whose
 * selling point is that it has none. Two hundred lines of a protocol that has
 * not changed since 2008 is the cheaper half of that trade.
 *
 * **What it does:** EHLO, STARTTLS where the server offers it, AUTH LOGIN or
 * PLAIN, one recipient, DATA, QUIT.
 *
 * **What it does not:** pipelining, 8BITMIME, DSN, several recipients, keeping
 * the connection open between messages. An admin sends two kinds of message,
 * one at a time, to one person. Every one of those would be code with no
 * caller -- and SMTP's failure modes are subtle enough without them.
 *
 * **Every reply is checked.** A server that refuses the recipient after
 * accepting the sender, or refuses DATA after accepting the recipient, has to
 * be noticed at that step: SMTP will happily carry on with a conversation that
 * has already gone wrong, and the message simply never arrives.
 *
 * **The password is never in an exception.** A failure names the step and the
 * server's own reply, and AUTH's failure says only that the credentials were
 * refused.
 */
final class SmtpMailer implements Mailer
{
    /** A reply line is `250 text`, or `250-text` while more lines follow. */
    private const string CRLF = "\r\n";

    /** @var Closure(): SmtpTransport */
    private readonly Closure $transport;

    /**
     * @param Closure(): SmtpTransport $transport a fresh connection per message, because a connection
     *                                            held between requests is a connection that times out
     *                                            between them
     */
    public function __construct(
        Closure $transport,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        /** `tls` issues STARTTLS, `ssl` expects an already-encrypted socket, `none` neither. */
        private readonly string $encryption = 'tls',
        /** What this client calls itself in EHLO. */
        private readonly string $clientName = 'localhost',
    ) {
        $this->transport = $transport;
    }

    public function send(Message $message): void
    {
        $smtp = ($this->transport)();
        $smtp->open();

        try {
            $this->expect($smtp, '220', 'greeting');
            $capabilities = $this->hello($smtp);

            if ($this->encryption === 'tls') {
                if (!isset($capabilities['STARTTLS'])) {
                    throw new MailException(
                        "The mail server does not offer STARTTLS, and 'tls' was configured. Either the "
                        . "server is not the one you meant, or mail.encryption should be 'ssl' (an "
                        . "encrypted socket, usually port 465) or 'none'.",
                    );
                }

                $this->command($smtp, 'STARTTLS', '220', 'STARTTLS');
                $smtp->startTls();

                // Again, because the capability list before encryption is not
                // the one that counts: AUTH is commonly offered only after.
                $capabilities = $this->hello($smtp);
            }

            if ($this->username !== null && $this->username !== '') {
                $this->authenticate($smtp, $capabilities);
            }

            $this->command($smtp, 'MAIL FROM:' . $message->from->envelope(), '250', 'sender');
            $this->command($smtp, 'RCPT TO:' . $message->to->envelope(), ['250', '251'], 'recipient');
            $this->command($smtp, 'DATA', '354', 'DATA');

            $smtp->write(self::stuff((new Mime($message))->full()) . self::CRLF . '.' . self::CRLF);
            $this->expect($smtp, '250', 'message');

            $this->command($smtp, 'QUIT', '221', 'QUIT');
        } finally {
            // Including on the way out of a failure: a half-finished
            // conversation left open holds a connection on the server until it
            // times out, and an admin that fails to send ten times has ten of
            // them.
            $smtp->close();
        }
    }

    /**
     * EHLO, and what the server says it can do.
     *
     * HELO is not attempted as a fallback. A server too old for EHLO is too
     * old for STARTTLS and AUTH, so the message would go out unencrypted and
     * unauthenticated -- which is not a fallback, it is a different and worse
     * thing happening silently.
     *
     * @return array<string, string> capability name => the rest of its line
     */
    private function hello(SmtpTransport $smtp): array
    {
        $smtp->write('EHLO ' . $this->clientName . self::CRLF);

        $capabilities = [];

        foreach ($this->reply($smtp, '250', 'EHLO') as $line) {
            // '250-AUTH LOGIN PLAIN' -> ['AUTH' => 'LOGIN PLAIN']
            $text = trim(substr($line, 4));

            if ($text === '') {
                continue;
            }

            [$name, $rest] = array_pad(explode(' ', $text, 2), 2, '');
            $capabilities[strtoupper($name)] = strtoupper($rest);
        }

        return $capabilities;
    }

    /**
     * AUTH, by whichever mechanism the server offers.
     *
     * LOGIN first because it is the one every server supports, PLAIN as the
     * fallback, and nothing else: CRAM-MD5 and friends are weaker than TLS
     * already is and exist to protect a password on an unencrypted link --
     * which is a link this client should not be sending a password over at
     * all.
     *
     * @param array<string, string> $capabilities
     */
    private function authenticate(SmtpTransport $smtp, array $capabilities): void
    {
        $offered = $capabilities['AUTH'] ?? '';
        $user = (string) $this->username;
        $secret = (string) $this->password;

        if ($offered !== '' && !str_contains($offered, 'LOGIN') && str_contains($offered, 'PLAIN')) {
            $this->command(
                $smtp,
                'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $secret),
                '235',
                'AUTH',
            );

            return;
        }

        $this->command($smtp, 'AUTH LOGIN', '334', 'AUTH');
        $this->command($smtp, base64_encode($user), '334', 'AUTH');
        $this->command($smtp, base64_encode($secret), '235', 'AUTH');
    }

    /**
     * Sends a command and insists on the code it is supposed to answer with.
     *
     * @param string|list<string> $expected
     */
    private function command(SmtpTransport $smtp, string $command, string|array $expected, string $step): void
    {
        $smtp->write($command . self::CRLF);
        $this->expect($smtp, $expected, $step);
    }

    /** @param string|list<string> $expected */
    private function expect(SmtpTransport $smtp, string|array $expected, string $step): void
    {
        $this->reply($smtp, $expected, $step);
    }

    /**
     * Reads a whole reply, continuation lines and all, and checks its code.
     *
     * A reply is one or more lines; all but the last have a dash after the
     * code. Reading only the first line is the classic bug: the rest stay in
     * the buffer and are read as the answer to the *next* command, so every
     * reply after a multi-line one is off by one and the error that eventually
     * surfaces describes the wrong step.
     *
     * @param  string|list<string> $expected
     * @return list<string>        the lines, codes included
     */
    private function reply(SmtpTransport $smtp, string|array $expected, string $step): array
    {
        $lines = [];

        do {
            $line = $smtp->readLine();
            $lines[] = $line;
            $more = \strlen($line) > 3 && $line[3] === '-';
        } while ($more);

        $code = substr($lines[0], 0, 3);
        $codes = \is_array($expected) ? $expected : [$expected];

        if (!\in_array($code, $codes, true)) {
            throw new MailException(\sprintf(
                "The mail server refused the %s step: it answered '%s' where %s was expected.",
                $step,
                // The server's own words, which are what a postmaster asks
                // for. They never contain the password: the only command
                // carrying one is AUTH, whose refusal is a bare '535'.
                implode(' ', $lines),
                implode(' or ', $codes),
            ));
        }

        return $lines;
    }

    /**
     * A line of a message that begins with a dot gets a second one.
     *
     * RFC 5321's transparency rule: a lone dot ends the message, so a body
     * line that starts with one has to be doubled or the server reads the rest
     * of the mail as commands. The bodies this SDK sends are base64 and the
     * alphabet has no dot in it -- so this never fires today, and it is here
     * because the day a driver sends something else is not the day to discover
     * the rule.
     */
    private static function stuff(string $message): string
    {
        return preg_replace('/^\./m', '..', $message) ?? $message;
    }
}
