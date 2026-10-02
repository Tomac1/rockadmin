<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

/**
 * A socket, opened with the streams PHP already has.
 *
 * `stream_socket_client()` rather than `fsockopen()` because it is the one
 * that takes a context -- which is what `ssl` and STARTTLS need -- and the one
 * whose timeout applies to connecting. The read timeout is set separately,
 * because the two are different failures: a server that cannot be reached and
 * a server that answers and then stops talking.
 *
 * **No certificate verification switch.** `stream_socket_enable_crypto()`
 * verifies by default and this does not offer a way to turn that off. A
 * verification-off option is the kind that gets copied into production from a
 * blog post, and what it switches off is the only thing distinguishing the
 * mail server from whoever is between the admin and it. A host with a private
 * CA sets `openssl.cafile` in php.ini, which is where that belongs.
 */
final class StreamTransport implements SmtpTransport
{
    /** @var resource|null */
    private $socket;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeoutSeconds = 10,
        /** `ssl` wraps the socket from the first byte; `tls` and `none` open it in the clear. */
        private readonly string $encryption = 'tls',
    ) {
    }

    public function open(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : '';
        $errorNumber = 0;
        $errorMessage = '';

        $socket = @stream_socket_client(
            $scheme . $this->host . ':' . $this->port,
            $errorNumber,
            $errorMessage,
            $this->timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            stream_context_create(),
        );

        if ($socket === false) {
            throw new MailException(\sprintf(
                'The mail server %s:%d could not be reached: %s. The admin cannot send anything until '
                . 'it can.',
                $this->host,
                $this->port,
                $errorMessage === '' ? "error {$errorNumber}" : $errorMessage,
            ));
        }

        // Separate from the connect timeout above, and the reason a hung
        // server does not hold a browser open until PHP's own limit.
        stream_set_timeout($socket, $this->timeoutSeconds);

        $this->socket = $socket;
    }

    public function readLine(): string
    {
        $socket = $this->socket ?? throw new MailException('Nothing has been connected to read from.');

        $line = fgets($socket, 1024);
        $info = stream_get_meta_data($socket);

        if ($info['timed_out']) {
            throw new MailException(\sprintf(
                'The mail server %s:%d stopped answering after %d seconds.',
                $this->host,
                $this->port,
                $this->timeoutSeconds,
            ));
        }

        if ($line === false) {
            throw new MailException(\sprintf(
                'The mail server %s:%d closed the connection without answering.',
                $this->host,
                $this->port,
            ));
        }

        return rtrim($line, "\r\n");
    }

    public function write(string $data): void
    {
        $socket = $this->socket ?? throw new MailException('Nothing has been connected to write to.');

        if (@fwrite($socket, $data) === false) {
            throw new MailException(\sprintf(
                'The connection to %s:%d was lost while sending.',
                $this->host,
                $this->port,
            ));
        }
    }

    public function startTls(): void
    {
        $socket = $this->socket ?? throw new MailException('Nothing has been connected to encrypt.');

        // The constant rather than a hard-coded method: it follows whatever
        // the installed OpenSSL considers current, so this does not have to be
        // edited the next time a TLS version is retired.
        $started = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        if ($started !== true) {
            throw new MailException(\sprintf(
                'STARTTLS with %s:%d failed. The admin stops here rather than carrying on in the '
                . 'clear, because carrying on means sending the mail password as text.',
                $this->host,
                $this->port,
            ));
        }
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
