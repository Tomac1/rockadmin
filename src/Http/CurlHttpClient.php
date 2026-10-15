<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use CurlHandle;

/**
 * The client used when `ext-curl` is loaded, which is almost everywhere.
 *
 * Preferred over the stream one for two reasons that both show up in
 * production rather than on a laptop: it does not need `allow_url_fopen`,
 * which hardened hosting turns off and which cannot be turned back on from
 * here; and it separates "could not connect" from "connected and the server
 * said no", which the stream wrapper reports as the same `false`.
 *
 * **The extension is not required.** `composer.json` names four extensions and
 * this is not one of them (rule 1); `HttpClients::best()` falls back when it
 * is missing.
 *
 * **Certificates are verified, and there is no switch.** An option to turn it
 * off is one that gets copied into production from a forum post, and what it
 * turns off is the only thing that distinguishes the object store from
 * whoever is between this admin and it.
 */
final class CurlHttpClient implements HttpClient
{
    public function __construct(
        /** Seconds to wait for the whole exchange. An upload is in it, so it is not short. */
        private readonly int $timeoutSeconds = 30,
        /** Seconds to wait for the connection alone. */
        private readonly int $connectSeconds = 10,
    ) {
    }

    public function send(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $handle = curl_init($url);

        if (!$handle instanceof CurlHandle) {
            throw new HttpClientException("'{$url}' is not a URL this client can open.");
        }

        $received = [];

        curl_setopt_array($handle, [
            // An empty method would make curl send a GET, which is not
            // what the caller asked for -- and is the one value its own type
            // refuses.
            CURLOPT_CUSTOMREQUEST => $method === '' ? 'GET' : $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => $this->connectSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => self::lines($headers),
            // Even for a GET: curl sends nothing when the body is empty, and
            // setting it unconditionally keeps one code path.
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $handle, string $line) use (&$received): int {
                $length = \strlen($line);
                $parts = explode(':', $line, 2);

                if (\count($parts) === 2) {
                    $received[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                // curl counts bytes, not lines: anything else aborts the
                // transfer with CURLE_WRITE_ERROR.
                return $length;
            },
        ]);

        $answer = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        curl_close($handle);

        if ($answer === false) {
            throw new HttpClientException(\sprintf(
                'The request to %s did not complete: %s',
                self::withoutQuery($url),
                $error === '' ? 'no reason given' : $error,
            ));
        }

        /** @var array<string, string> $received */
        return new HttpResponse(\is_int($status) ? $status : 0, \is_string($answer) ? $answer : '', $received);
    }

    /**
     * @param  array<string, string> $headers
     * @return list<string>
     */
    private static function lines(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }

    /**
     * A URL safe to put in an exception.
     *
     * A presigned URL carries the signature and the credential in its query
     * string, and an exception message is the one place a URL reliably ends
     * up in a log somebody else can read.
     */
    private static function withoutQuery(string $url): string
    {
        $cut = strpos($url, '?');

        return $cut === false ? $url : substr($url, 0, $cut);
    }
}
