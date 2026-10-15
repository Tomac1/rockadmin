<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\HttpClient;
use RockAdmin\Http\HttpClientException;
use RockAdmin\Http\HttpResponse;
use RockAdmin\Storage\S3Storage;
use RockAdmin\Storage\SignatureV4;
use RockAdmin\Storage\StorageException;

/**
 * What actually goes over the wire, against a store that answers from a list.
 *
 * `HttpClient` is a seam for this: the four verbs, the headers, the payload
 * hash and every way a store can say no are provable in a millisecond here,
 * and otherwise only against somebody's bucket. The signature itself has its
 * own test against AWS's published vector; this is about the request built
 * around it.
 *
 * The configuration under test is R2's — an account endpoint, path-style
 * addressing, `auto` as the region — because that is what the milestone was
 * asked for. Nothing in the class knows that: the same assertions hold for
 * MinIO with a different endpoint.
 */
#[CoversClass(S3Storage::class)]
final class S3StorageTest extends TestCase
{
    public function testPuttingAFileAddressesTheBucketPathStyleAndSignsTheBytes(): void
    {
        $store = $this->store([new HttpResponse(200)]);

        $store->storage->put('ads/2026/10/8f3a.jpg', 'the-bytes', 'image/jpeg');

        $sent = $store->client->sent[0];

        self::assertSame('PUT', $sent['method']);
        self::assertSame(
            'https://account.r2.cloudflarestorage.com/cyklobazar/ads/2026/10/8f3a.jpg',
            $sent['url'],
            'Bucket-in-the-host addressing: R2 account endpoints want the bucket in the path.',
        );
        self::assertSame('the-bytes', $sent['body']);
        self::assertSame('image/jpeg', $sent['headers']['content-type'] ?? null);
        self::assertSame('9', $sent['headers']['content-length'] ?? null);
        self::assertSame(
            hash('sha256', 'the-bytes'),
            $sent['headers']['x-amz-content-sha256'] ?? null,
            'The payload hash must be of the bytes actually sent, or the store refuses the signature.',
        );
        self::assertStringStartsWith('AWS4-HMAC-SHA256 Credential=', $sent['headers']['Authorization'] ?? '');
    }

    public function testReadingAndDeletingSignTheEmptyPayload(): void
    {
        $store = $this->store([new HttpResponse(200, 'the-bytes'), new HttpResponse(204)]);

        self::assertSame('the-bytes', $store->storage->get('ads/a.jpg'));
        $store->storage->delete('ads/a.jpg');

        foreach ($store->client->sent as $sent) {
            self::assertSame(SignatureV4::EMPTY_PAYLOAD, $sent['headers']['x-amz-content-sha256'] ?? null);
            self::assertSame('', $sent['body']);
        }

        self::assertSame(['GET', 'DELETE'], array_column($store->client->sent, 'method'));
    }

    public function testExistsAsksWithHeadAndDoesNotFetchTheFile(): void
    {
        $store = $this->store([new HttpResponse(200), new HttpResponse(404)]);

        self::assertTrue($store->storage->exists('ads/a.jpg'));
        self::assertFalse($store->storage->exists('ads/b.jpg'));
        self::assertSame(['HEAD', 'HEAD'], array_column($store->client->sent, 'method'));
    }

    public function testDeletingSomethingAlreadyGoneIsNotAFailure(): void
    {
        $store = $this->store([new HttpResponse(404, '<Error><Code>NoSuchKey</Code></Error>')]);

        $this->expectNotToPerformAssertions();

        $store->storage->delete('ads/a.jpg');
    }

    public function testReadingSomethingThatIsNotThereSaysSoWithoutTheStoresXml(): void
    {
        $store = $this->store([new HttpResponse(404, '<Error><Code>NoSuchKey</Code></Error>')]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("no file stored as 'ads/a.jpg'");

        $store->storage->get('ads/a.jpg');
    }

    /**
     * A 403 is the message that matters, because it is the one that will
     * happen: four different mistakes produce it and the store will not say
     * which.
     */
    public function testARefusedWriteNamesTheLikelyCauses(): void
    {
        $store = $this->store([new HttpResponse(403, '<Error><Code>SignatureDoesNotMatch</Code></Error>')]);

        try {
            $store->storage->put('ads/a.jpg', 'x', 'image/jpeg');

            self::fail('A 403 was treated as a successful upload.');
        } catch (StorageException $e) {
            self::assertStringContainsString('403', $e->getMessage());
            self::assertStringContainsString('access key', $e->getMessage());
            self::assertStringContainsString('clock', $e->getMessage());
            self::assertStringContainsString('SignatureDoesNotMatch', $e->getMessage(), "The store's own words.");
        }
    }

    public function testTheSecretIsNeverInAnError(): void
    {
        $store = $this->store([new HttpResponse(403, 'denied')]);

        try {
            $store->storage->put('ads/a.jpg', 'x', 'image/jpeg');
        } catch (StorageException $e) {
            self::assertStringNotContainsString('wJalrXUtnFEMI', $e->getMessage());
        }
    }

    public function testAStoreThatCannotBeReachedIsADifferentSentenceFromOneThatSaidNo(): void
    {
        $store = $this->store([new HttpClientException('Could not resolve host')]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('could not be reached');

        $store->storage->get('ads/a.jpg');
    }

    public function testAKeyThatIsNotOneNeverReachesTheNetwork(): void
    {
        $store = $this->store([new HttpResponse(200)]);

        try {
            $store->storage->get('../../etc/passwd');

            self::fail('That key was fetched.');
        } catch (StorageException $e) {
            self::assertStringContainsString('not a storage key', $e->getMessage());
        }

        self::assertSame([], $store->client->sent, 'The request was sent before the key was checked.');
    }

    // --- reading is a link, not a download ---

    public function testUrlSignsAGetAndAsksTheStoreNothing(): void
    {
        $store = $this->store([]);

        $url = $store->storage->url('ads/2026/10/8f3a.jpg');

        self::assertStringStartsWith(
            'https://account.r2.cloudflarestorage.com/cyklobazar/ads/2026/10/8f3a.jpg?',
            $url,
        );
        self::assertStringContainsString('X-Amz-Signature=', $url);
        self::assertStringContainsString('X-Amz-Expires=600', $url);
        self::assertSame(
            [],
            $store->client->sent,
            'Signing is arithmetic: a page that shows twenty images must not make twenty requests.',
        );
    }

    public function testAPublicBucketIsServedDirectlyAndSignsNothing(): void
    {
        $storage = new S3Storage(
            new ScriptedHttpClient([]),
            $this->signer(),
            'https://account.r2.cloudflarestorage.com',
            'cyklobazar',
            600,
            'https://cdn.cyklobazar.cz',
        );

        self::assertSame('https://cdn.cyklobazar.cz/ads/a.jpg', $storage->url('ads/a.jpg'));
    }

    /** @param list<HttpResponse|HttpClientException> $answers */
    private function store(array $answers): ScriptedStore
    {
        $client = new ScriptedHttpClient($answers);

        return new ScriptedStore($client, new S3Storage(
            $client,
            $this->signer(),
            'https://account.r2.cloudflarestorage.com',
            'cyklobazar',
        ));
    }

    private function signer(): SignatureV4
    {
        return new SignatureV4('AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', 'auto');
    }
}

/** A driver and the client under it, so a test can assert on both. */
final class ScriptedStore
{
    public function __construct(
        public readonly ScriptedHttpClient $client,
        public readonly S3Storage $storage,
    ) {
    }
}

/**
 * A store that answers from a list and writes down what it was asked.
 *
 * `SmtpMailerTest`'s scripted server in another shape, and for the same
 * reason: what goes wrong with an object store is a header, an order and a
 * hash, and all three are visible here and nowhere else without a bucket.
 */
final class ScriptedHttpClient implements HttpClient
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    public array $sent = [];

    private int $next = 0;

    /** @param list<HttpResponse|HttpClientException> $answers */
    public function __construct(private readonly array $answers)
    {
    }

    public function send(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        $answer = $this->answers[$this->next++]
            ?? throw new HttpClientException('The client asked more than this store was scripted to answer.');

        if ($answer instanceof HttpClientException) {
            throw $answer;
        }

        return $answer;
    }
}
