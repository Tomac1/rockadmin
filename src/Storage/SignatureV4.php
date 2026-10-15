<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

use DateTimeImmutable;
use DateTimeZone;

/**
 * AWS Signature Version 4, which is how an S3-compatible store knows who is
 * asking.
 *
 * It is four hashes and a lot of exactness. Every byte of the canonical
 * request is part of the signature — the order of the headers, the case of
 * their names, the spaces around their values, whether the path is encoded
 * once or twice — so there is no "nearly right": a signature with one
 * character out fails identically to a wrong secret, and no server will tell
 * you which.
 *
 * That is also why this is its own class with its own test. The test asserts
 * against AWS's own published vector; if this file is ever edited and that
 * test still passes, the edit was safe, and if it fails, nothing else needs
 * looking at.
 *
 * **Nothing here talks to a network.** Signing is arithmetic: a presigned URL
 * is built out of a secret and a clock, which is what makes it cheap enough to
 * do on every page render.
 */
final class SignatureV4
{
    private const string ALGORITHM = 'AWS4-HMAC-SHA256';

    /** The hash of an empty body, which every GET and DELETE sends. */
    public const string EMPTY_PAYLOAD = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /** What a presigned URL puts where the payload hash would go. */
    public const string UNSIGNED_PAYLOAD = 'UNSIGNED-PAYLOAD';

    public function __construct(
        private readonly string $accessKey,
        private readonly string $secret,
        private readonly string $region,
        private readonly string $service = 's3',
    ) {
    }

    /**
     * The headers a signed request carries, including the `Authorization` one.
     *
     * The caller's headers come back too, so there is one array to hand to the
     * client rather than two to merge at the call site — merging them there is
     * how a header gets signed and then not sent, which fails as a signature
     * mismatch and reads as a credentials problem.
     *
     * @param  array<string, string> $headers the caller's own; `host` is added here
     * @return array<string, string>
     */
    public function headersFor(
        string $method,
        string $url,
        array $headers,
        string $payloadHash,
        ?DateTimeImmutable $at = null,
    ): array {
        $at = ($at ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));
        $parts = self::partsOf($url);
        $host = \is_string($parts['host'] ?? null) ? $parts['host'] : '';

        if (isset($parts['port']) && \is_int($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        $headers = [
            ...$headers,
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $at->format('Ymd\THis\Z'),
        ];

        $canonical = $this->canonicalRequest(
            $method,
            self::pathOf($parts),
            self::queryOf($parts),
            $headers,
            $payloadHash,
        );

        $scope = $at->format('Ymd') . '/' . $this->region . '/' . $this->service . '/aws4_request';
        $signature = $this->signatureOf($this->stringToSign($at, $scope, $canonical), $at);

        $headers['Authorization'] = \sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            self::ALGORITHM,
            $this->accessKey,
            $scope,
            implode(';', array_keys(self::canonicalHeaders($headers))),
            $signature,
        );

        return $headers;
    }

    /**
     * A URL that works on its own for a few minutes.
     *
     * The signature goes in the query string rather than in a header, which is
     * what makes it something a browser can follow — and what makes it
     * something to keep out of logs and exception messages, since the URL *is*
     * the credential until it expires.
     *
     * `UNSIGNED-PAYLOAD` rather than a hash: the body is whatever the object
     * turns out to be, and nobody has it to hash when the link is built.
     */
    public function presignedUrl(
        string $method,
        string $url,
        int $expiresInSeconds,
        ?DateTimeImmutable $at = null,
    ): string {
        $at = ($at ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));
        $parts = self::partsOf($url);
        $host = \is_string($parts['host'] ?? null) ? $parts['host'] : '';

        if (isset($parts['port']) && \is_int($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        $scope = $at->format('Ymd') . '/' . $this->region . '/' . $this->service . '/aws4_request';

        $query = [
            'X-Amz-Algorithm' => self::ALGORITHM,
            'X-Amz-Credential' => $this->accessKey . '/' . $scope,
            'X-Amz-Date' => $at->format('Ymd\THis\Z'),
            'X-Amz-Expires' => (string) $expiresInSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];

        $canonical = $this->canonicalRequest(
            $method,
            self::pathOf($parts),
            self::canonicalQuery($query),
            ['host' => $host],
            self::UNSIGNED_PAYLOAD,
        );

        $query['X-Amz-Signature'] = $this->signatureOf($this->stringToSign($at, $scope, $canonical), $at);

        $scheme = \is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $base = $scheme . '://' . $host . self::pathOf($parts);

        return $base . '?' . self::canonicalQuery($query);
    }

    /**
     * The canonical request: six lines, and every one of them exact.
     *
     * The query is canonicalised here rather than being assumed to arrive
     * that way. Sorting an already-sorted query is a no-op, and the
     * alternative is a method that answers a perfectly plausible string for
     * `Param2=b&Param1=a` and produces a signature the server refuses -- which
     * costs a 403 that looks exactly like a wrong secret. A class where being
     * slightly wrong is indistinguishable from being locked out should not
     * have a footgun in its public API.
     *
     * @param array<string, string> $headers
     */
    public function canonicalRequest(
        string $method,
        string $path,
        string $query,
        array $headers,
        string $payloadHash,
    ): string {
        $query = self::canonicalQueryString($query);
        $canonicalHeaders = self::canonicalHeaders($headers);
        $lines = [];

        foreach ($canonicalHeaders as $name => $value) {
            $lines[] = $name . ':' . $value;
        }

        return implode("\n", [
            strtoupper($method),
            $path === '' ? '/' : $path,
            $query,
            implode("\n", $lines),
            '',
            implode(';', array_keys($canonicalHeaders)),
            $payloadHash,
        ]);
    }

    /** The string the signing key is applied to. */
    public function stringToSign(DateTimeImmutable $at, string $scope, string $canonicalRequest): string
    {
        return implode("\n", [
            self::ALGORITHM,
            $at->format('Ymd\THis\Z'),
            $scope,
            hash('sha256', $canonicalRequest),
        ]);
    }

    /**
     * The four-stage signing key, then the signature.
     *
     * Each stage is keyed by the one before it, which is what makes a leaked
     * daily key useless for another day, another region or another service.
     *
     * Public because this is the one thing in the class worth asserting
     * against a constant: AWS publishes a test vector, and a test can only
     * reach it by signing a canonical request it built itself. A signing
     * primitive is safe to expose -- it is given the string to sign and
     * answers a hex digest; the secret stays where it was.
     */
    public function signatureOf(string $stringToSign, DateTimeImmutable $at): string
    {
        $key = hash_hmac('sha256', $at->format('Ymd'), 'AWS4' . $this->secret, true);
        $key = hash_hmac('sha256', $this->region, $key, true);
        $key = hash_hmac('sha256', $this->service, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);

        return hash_hmac('sha256', $stringToSign, $key);
    }

    /**
     * Headers lower-cased, trimmed, sorted by name.
     *
     * Sorted because the server sorts them before checking, trimmed because it
     * trims, and lower-cased because HTTP header names are case-insensitive
     * and a signature is not.
     *
     * @param  array<string, string> $headers
     * @return array<string, string>
     */
    private static function canonicalHeaders(array $headers): array
    {
        $canonical = [];

        foreach ($headers as $name => $value) {
            $name = strtolower(trim($name));

            if ($name === 'authorization') {
                continue;
            }

            // Runs of whitespace inside a value collapse to one space, which
            // is what the specification calls "trimall" and what a proxy may
            // do to the value in transit.
            $canonical[$name] = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        }

        ksort($canonical);

        return $canonical;
    }

    /**
     * A URL taken apart, or a refusal.
     *
     * `parse_url()` answers false for something it cannot read at all, and
     * carrying that forward as an empty host would produce a signature over a
     * request to nowhere -- which fails at the store as a mismatch and reads
     * as bad credentials.
     *
     * @return array<string, mixed>
     */
    private static function partsOf(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || !\is_string($parts['host'] ?? null)) {
            throw new StorageException("'{$url}' is not a URL that can be signed: it names no host.");
        }

        return $parts;
    }

    /**
     * The path, encoded the way S3 wants it.
     *
     * Each segment is percent-encoded and the separators are not, which is
     * what `rawurlencode` per segment gives. Every key this project writes is
     * already made of characters that encode to themselves, so this is a no-op
     * in practice -- and it is here for the day somebody stores a key that is
     * not.
     *
     * @param array<string, mixed> $parts
     */
    private static function pathOf(array $parts): string
    {
        $path = \is_string($parts['path'] ?? null) ? $parts['path'] : '/';

        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /** @param array<string, mixed> $parts */
    private static function queryOf(array $parts): string
    {
        return self::canonicalQueryString(\is_string($parts['query'] ?? null) ? $parts['query'] : '');
    }

    /** A query string as written, re-emitted sorted and encoded the one way both sides agree on. */
    private static function canonicalQueryString(string $query): string
    {
        if ($query === '') {
            return '';
        }

        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $pairs[rawurldecode($name)] = rawurldecode($value);
        }

        return self::canonicalQuery($pairs);
    }

    /**
     * Query parameters sorted by name and encoded, which is how both sides
     * agree on what was asked for.
     *
     * @param array<string, string> $query
     */
    private static function canonicalQuery(array $query): string
    {
        ksort($query);

        $pairs = [];

        foreach ($query as $name => $value) {
            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        return implode('&', $pairs);
    }
}
