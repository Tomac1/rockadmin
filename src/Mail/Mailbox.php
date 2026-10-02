<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

/**
 * One address, with the name it is shown under.
 *
 * A value object rather than a string, because "Jana Nováková
 * <jana@example.com>" has to be assembled correctly in two different places
 * -- a header, where the name may need encoding, and the SMTP envelope, where
 * only the address goes and the name would be a syntax error. A string would
 * have to be taken apart again in the second place, by a regular expression,
 * on data that came from configuration.
 */
final class Mailbox
{
    public function __construct(
        public readonly string $address,
        public readonly string $name = '',
    ) {
        if (!self::isAddress($address)) {
            throw new MailException(\sprintf(
                "'%s' is not an e-mail address this admin will send to. It needs a local part, an "
                . '@ and a domain, and no line breaks -- a header that carries one of those is a '
                . 'header somebody else can add their own to.',
                $address,
            ));
        }
    }

    /**
     * What goes in a `From:` or `To:` header, with the name encoded if it
     * needs it.
     */
    public function header(): string
    {
        if ($this->name === '') {
            return $this->address;
        }

        return Mime::encodeWord($this->name) . ' <' . $this->address . '>';
    }

    /** What goes in `MAIL FROM:` and `RCPT TO:`, which is the address alone. */
    public function envelope(): string
    {
        return '<' . $this->address . '>';
    }

    /**
     * Deliberately not RFC 5322 in full.
     *
     * That grammar admits comments, quoted local parts and bracketed address
     * literals, and a validator written to it accepts things no mail server in
     * this decade will. What matters here is the half that is a security
     * question: a value with a newline in it ends the header and starts
     * another, which is how a `Bcc:` arrives that nobody wrote.
     */
    private static function isAddress(string $address): bool
    {
        return $address !== ''
            && preg_match('/^[^\s<>@,;:"]+@[^\s<>@,;:"]+\.[^\s<>@,;:"]+$/', $address) === 1;
    }
}
