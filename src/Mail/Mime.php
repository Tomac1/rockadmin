<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

use DateTimeImmutable;

/**
 * One message as bytes: headers, a multipart body, and the two together.
 *
 * All three drivers that actually assemble a message need exactly this, and
 * each assembling it for itself would be three spellings of MIME in one SDK --
 * which is three chances for the boundary in the header to disagree with the
 * boundary in the body, a mistake that looks like a mail client bug.
 *
 * **`multipart/alternative`, text first.** The order is the specification's,
 * not a preference: a client shows the last part it understands, so the HTML
 * half goes second or nobody ever sees it.
 *
 * **Base64 rather than quoted-printable**, for both halves. Quoted-printable
 * is the better-looking encoding and is a page of rules about soft line
 * breaks, trailing whitespace and which characters must be escaped where;
 * base64 is four lines of code that cannot be subtly wrong, and its alphabet
 * contains no dot, so a line never needs stuffing and never ends a DATA
 * command early.
 *
 * **CRLF everywhere.** A mail message's line ending is CRLF whatever the
 * platform thinks, and a lone LF in a header is how a message gets truncated
 * by one server and rejected by the next.
 */
final class Mime
{
    /** RFC 2045: a base64 body line is at most 76 characters. */
    private const int LINE = 76;

    private const string CRLF = "\r\n";

    private readonly string $boundary;

    private readonly DateTimeImmutable $at;

    public function __construct(
        private readonly Message $message,
        ?DateTimeImmutable $at = null,
        ?string $boundary = null,
    ) {
        $this->at = $at ?? new DateTimeImmutable();

        // Random, because a boundary that appears inside a part ends it early.
        // Base64's alphabet cannot produce this prefix, so the collision the
        // standard worries about cannot happen here -- the randomness is for
        // the day a part is encoded some other way.
        $this->boundary = $boundary ?? '=_ra_' . bin2hex(random_bytes(12));
    }

    /**
     * The headers, in the order they are written.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Date' => $this->at->format('r'),
            'From' => $this->message->from->header(),
            'To' => $this->message->to->header(),
            'Subject' => self::encodeWord($this->message->subject),
            'Message-ID' => $this->messageId(),
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/alternative; boundary="' . $this->boundary . '"',
        ];
    }

    /**
     * The headers as a block, optionally without the ones a transport writes
     * itself.
     *
     * `mail()` is given the recipient and the subject as arguments and adds
     * both headers on its own; passing them again produces a message with two
     * `Subject:` lines, which some clients show and some ignore.
     *
     * @param list<string> $without header names to leave out, case-insensitively
     */
    public function headerBlock(array $without = []): string
    {
        $skip = array_map(strtolower(...), $without);
        $lines = [];

        foreach ($this->headers() as $name => $value) {
            if (\in_array(strtolower($name), $skip, true)) {
                continue;
            }

            $lines[] = $name . ': ' . $value;
        }

        return implode(self::CRLF, $lines);
    }

    /** The multipart body: the preamble, both parts, and the closing boundary. */
    public function body(): string
    {
        return implode(self::CRLF, [
            // Read only by a client too old to understand multipart at all.
            // It is here because such a client otherwise shows the raw
            // boundaries, and one sentence is friendlier than that.
            'This message is in both plain text and HTML.',
            '',
            '--' . $this->boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $this->encode($this->message->text),
            '--' . $this->boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $this->encode($this->message->html),
            '--' . $this->boundary . '--',
            '',
        ]);
    }

    /** The whole message, as a mail client or an SMTP server receives it. */
    public function full(): string
    {
        return $this->headerBlock() . self::CRLF . self::CRLF . $this->body();
    }

    /**
     * A header value that may carry anything, encoded so that it carries it.
     *
     * RFC 2047 encoded words, and only when they are needed: `Password reset`
     * must stay readable in a log file and in a mail client's raw view, while
     * `Obnovení hesla` has to be encoded or it arrives as mojibake on the one
     * server that still assumes Latin-1.
     *
     * The whole value is encoded as one word rather than split at 75
     * characters. A subject long enough to need splitting is a subject to
     * shorten, and the splitting rule -- which must not cut a multi-byte
     * character in half -- is the part that goes wrong in Czech.
     */
    public static function encodeWord(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function encode(string $body): string
    {
        return chunk_split(base64_encode($body), self::LINE, self::CRLF);
    }

    /**
     * A unique id, in the sender's domain.
     *
     * Not strictly required -- a server adds one if it is missing -- but a
     * message with no id is a message that cannot be followed through a log,
     * and that is the question asked when somebody says the mail never
     * arrived.
     */
    private function messageId(): string
    {
        $at = explode('@', $this->message->from->address);
        $domain = $at[1] ?? 'localhost';

        return '<' . $this->at->format('YmdHis') . '.' . bin2hex(random_bytes(8)) . '@' . $domain . '>';
    }
}
