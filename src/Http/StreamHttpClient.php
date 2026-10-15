<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * The client for an installation without `ext-curl`.
 *
 * PHP's own HTTPS stream wrapper: it does TLS, chunked transfer encoding and
 * the header parsing, which is the half of HTTP nobody should write twice.
 * What it cannot do is tell a refused connection from a refused request -- it
 * answers `false` for both -- so the status line is read out of
 * `$http_response_header` and an empty one means the first.
 *
 * It needs `allow_url_fopen`, which some hosting turns off; `HttpClients`
 * prefers curl where it exists for exactly that reason, and this is the
 * fallback rather than the default.
 *
 * Verification is on, as it is by default in PHP 8, and there is no switch
 * here to turn it off.
 */
final class StreamHttpClient implements HttpClient
{
    public function __construct(private readonly int $timeoutSeconds = 30)
    {
    }

    public function send(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $lines),
            'content' => $body,
            'timeout' => $this->timeoutSeconds,
            // Without this a 404 is an exception from the wrapper and the
            // body -- which says what the object store actually objected to
            // -- is thrown away before anything can read it.
            'ignore_errors' => true,
            'follow_location' => 0,
            'protocol_version' => 1.1,
        ]]);

        // `$http_response_header` is set by the wrapper in this scope, and
        // only when a reply arrived: on a connection that failed it stays
        // undefined, which is the distinction this client exists to keep.
        $http_response_header = [];
        $answer = @file_get_contents($url, false, $context);
        /** @var list<string> $responseHeaders */
        $responseHeaders = $http_response_header;

        if ($responseHeaders === []) {
            throw new HttpClientException(\sprintf(
                'The request to %s did not complete: no reply, which is a connection that failed '
                . 'rather than a server that said no.',
                self::withoutQuery($url),
            ));
        }

        return new HttpResponse(
            self::statusOf($responseHeaders),
            $answer === false ? '' : $answer,
            self::headersOf($responseHeaders),
        );
    }

    /** @param list<string> $lines */
    private static function statusOf(array $lines): int
    {
        // The last status line, not the first: a 100-continue or a redirect
        // the wrapper followed leaves earlier ones in the array.
        $status = 0;

        foreach ($lines as $line) {
            if (preg_match('~^HTTP/\d(?:\.\d)?\s+(\d{3})~', $line, $found) === 1) {
                $status = (int) $found[1];
            }
        }

        return $status;
    }

    /**
     * @param  list<string>          $lines
     * @return array<string, string>
     */
    private static function headersOf(array $lines): array
    {
        $headers = [];

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);

            if (\count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return $headers;
    }

    /** A presigned URL carries its signature in the query string; an exception does not need it. */
    private static function withoutQuery(string $url): string
    {
        $cut = strpos($url, '?');

        return $cut === false ? $url : substr($url, 0, $cut);
    }
}
