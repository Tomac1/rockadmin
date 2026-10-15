<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Storage;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Storage\SignatureV4;

/**
 * The one part of talking to an object store that cannot be nearly right.
 *
 * A signature with one byte out fails exactly like a wrong secret, and no
 * server says which — so this is tested against AWS's own published vector
 * rather than against itself. The constants below are from the Signature
 * Version 4 test suite (`get-vanilla` and its query variant), and they were
 * confirmed before being written down here by computing the same four stages
 * in a second language: a reading of the specification that shares no code
 * with `SignatureV4`. Two independent readings agreeing on a 64-character hex
 * string is as close to proof as this gets without a network.
 *
 * The vector signs for service `service` rather than `s3`, which is why the
 * signer below is built with it: the suite is about the algorithm, and the
 * service name is one of its inputs.
 */
#[CoversClass(SignatureV4::class)]
final class SignatureV4Test extends TestCase
{
    private const string ACCESS_KEY = 'AKIDEXAMPLE';

    private const string SECRET = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';

    private const string SCOPE = '20150830/us-east-1/service/aws4_request';

    /** The published signature for `get-vanilla`. */
    private const string VANILLA = '5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31';

    /** The published signature for the same request with two query parameters. */
    private const string WITH_QUERY = 'b97d918cfa904a5beff61c982a1b6f458b799221646efd99d3219ec94cdf2500';

    public function testTheCanonicalRequestIsExactlyTheOneTheSuitePublishes(): void
    {
        // Six lines, and every byte of them is in the signature: the order of
        // the headers, the blank line after them, the trailing payload hash.
        self::assertSame(
            "GET\n"
            . "/\n"
            . "\n"
            . "host:example.amazonaws.com\n"
            . "x-amz-date:20150830T123600Z\n"
            . "\n"
            . "host;x-amz-date\n"
            . SignatureV4::EMPTY_PAYLOAD,
            $this->vanillaRequest(''),
        );
    }

    public function testTheSignatureMatchesThePublishedVector(): void
    {
        self::assertSame(self::VANILLA, $this->signatureFor($this->vanillaRequest('')));
    }

    public function testTheSignatureMatchesWithAQueryString(): void
    {
        self::assertSame(
            self::WITH_QUERY,
            $this->signatureFor($this->vanillaRequest('Param1=value1&Param2=value2')),
        );
    }

    public function testQueryParametersAreSortedBeforeSigning(): void
    {
        // The server sorts them before checking, so a request that signed them
        // in the order they were written would fail for anybody who wrote them
        // in any other order.
        self::assertSame(
            $this->signatureFor($this->vanillaRequest('Param1=value1&Param2=value2')),
            $this->signatureFor($this->vanillaRequest('Param2=value2&Param1=value1')),
        );
    }

    public function testHeadersAreLowerCasedTrimmedAndSorted(): void
    {
        $signer = $this->signer();

        $tidy = $signer->canonicalRequest(
            'PUT',
            '/bucket/a.jpg',
            '',
            ['host' => 'example.com', 'x-amz-date' => '20150830T123600Z', 'content-type' => 'image/jpeg'],
            SignatureV4::EMPTY_PAYLOAD,
        );

        $untidy = $signer->canonicalRequest(
            'PUT',
            '/bucket/a.jpg',
            '',
            ['X-Amz-Date' => ' 20150830T123600Z ', 'Content-Type' => 'image/jpeg', 'Host' => 'example.com'],
            SignatureV4::EMPTY_PAYLOAD,
        );

        self::assertSame($tidy, $untidy);
        self::assertStringContainsString('content-type;host;x-amz-date', $tidy);
    }

    public function testTheAuthorizationHeaderNamesEveryHeaderItSigned(): void
    {
        // A header signed and then not sent, or sent and not signed, fails as
        // a signature mismatch and reads as a credentials problem -- which is
        // why `headersFor()` returns the headers to send rather than leaving
        // the caller to merge two arrays.
        $headers = $this->signer()->headersFor(
            'PUT',
            'https://account.r2.cloudflarestorage.com/bucket/ads/a.jpg',
            ['content-type' => 'image/jpeg'],
            hash('sha256', 'the-bytes'),
            $this->at(),
        );

        self::assertArrayHasKey('Authorization', $headers);
        self::assertStringContainsString(
            'SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date',
            $headers['Authorization'],
        );
        self::assertSame('account.r2.cloudflarestorage.com', $headers['host']);
        self::assertSame(hash('sha256', 'the-bytes'), $headers['x-amz-content-sha256']);
        self::assertSame('20150830T123600Z', $headers['x-amz-date']);
    }

    public function testTheSecretIsNeverInWhatIsSent(): void
    {
        $headers = $this->signer()->headersFor(
            'GET',
            'https://account.r2.cloudflarestorage.com/bucket/a.jpg',
            [],
            SignatureV4::EMPTY_PAYLOAD,
            $this->at(),
        );

        foreach ($headers as $value) {
            self::assertStringNotContainsString(self::SECRET, $value);
        }
    }

    // --- links a browser can follow ---

    public function testAPresignedUrlCarriesEverythingTheServerNeedsToCheckIt(): void
    {
        $url = $this->signer()->presignedUrl(
            'GET',
            'https://account.r2.cloudflarestorage.com/bucket/ads/a.jpg',
            600,
            $this->at(),
        );

        self::assertStringStartsWith(
            'https://account.r2.cloudflarestorage.com/bucket/ads/a.jpg?',
            $url,
        );

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('AWS4-HMAC-SHA256', $query['X-Amz-Algorithm'] ?? null);
        self::assertSame(self::ACCESS_KEY . '/' . self::SCOPE, $query['X-Amz-Credential'] ?? null);
        self::assertSame('20150830T123600Z', $query['X-Amz-Date'] ?? null);
        self::assertSame('600', $query['X-Amz-Expires'] ?? null);
        self::assertSame('host', $query['X-Amz-SignedHeaders'] ?? null);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $this->signatureIn($url));
    }

    public function testAPresignedUrlIsStableForTheSameMinuteAndDiffersWithTheKey(): void
    {
        $signer = $this->signer();
        $first = $signer->presignedUrl('GET', 'https://host.example/bucket/a.jpg', 600, $this->at());
        $same = $signer->presignedUrl('GET', 'https://host.example/bucket/a.jpg', 600, $this->at());
        $other = $signer->presignedUrl('GET', 'https://host.example/bucket/b.jpg', 600, $this->at());

        self::assertSame($first, $same, 'Signing is arithmetic and must not drift.');
        self::assertNotSame($first, $other);
    }

    public function testAPresignedUrlExpiresBySayingSo(): void
    {
        $signer = $this->signer();
        $short = $signer->presignedUrl('GET', 'https://host.example/bucket/a.jpg', 60, $this->at());
        $long = $signer->presignedUrl('GET', 'https://host.example/bucket/a.jpg', 3600, $this->at());

        // Not merely a different parameter: the expiry is inside the
        // signature, so a URL whose lifetime is edited stops working.
        self::assertNotSame(
            $this->signatureIn($short),
            $this->signatureIn($long),
            'The expiry is not signed, so anybody could extend it by editing the URL.',
        );
    }

    private function vanillaRequest(string $query): string
    {
        return $this->signer()->canonicalRequest(
            'GET',
            '/',
            $query,
            ['host' => 'example.amazonaws.com', 'x-amz-date' => '20150830T123600Z'],
            SignatureV4::EMPTY_PAYLOAD,
        );
    }

    private function signatureFor(string $canonicalRequest): string
    {
        $signer = $this->signer();

        return $signer->signatureOf(
            $signer->stringToSign($this->at(), self::SCOPE, $canonicalRequest),
            $this->at(),
        );
    }

    private function signatureIn(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return \is_string($query['X-Amz-Signature'] ?? null) ? $query['X-Amz-Signature'] : '';
    }

    private function signer(): SignatureV4
    {
        return new SignatureV4(self::ACCESS_KEY, self::SECRET, 'us-east-1', 'service');
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2015-08-30 12:36:00', new DateTimeZone('UTC'));
    }
}
