<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\HttpClients;
use RockAdmin\Storage\S3Storage;
use RockAdmin\Storage\SignatureV4;
use RockAdmin\Storage\StorageException;

/**
 * The driver against a real bucket, when there is one.
 *
 * Everything else about S3 is proved without a network -- the signature
 * against AWS's published vector, the requests against a scripted client --
 * and both of those would stay green if the endpoint spelling were wrong, if
 * R2 wanted a header nobody documented, or if a presigned URL were rejected
 * for a reason only a server knows. That is what this is for.
 *
 * It runs only when `RA_TEST_S3_*` is set, the way the database tests run only
 * when a DSN is. Set them against an R2 bucket, a MinIO container or anything
 * else speaking the protocol:
 *
 *     RA_TEST_S3_ENDPOINT=https://<account>.r2.cloudflarestorage.com
 *     RA_TEST_S3_BUCKET=rockadmin-test
 *     RA_TEST_S3_KEY=...
 *     RA_TEST_S3_SECRET=...
 *     RA_TEST_S3_REGION=auto
 *
 * **It writes and deletes.** Point it at a bucket whose contents nobody minds
 * losing; every key it uses is under `ra-test/` and carries a random name, and
 * each test removes what it wrote.
 */
#[CoversClass(S3Storage::class)]
final class S3Test extends TestCase
{
    private ?S3Storage $storage = null;

    private string $key = '';

    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = getenv('RA_TEST_S3_ENDPOINT');
        $bucket = getenv('RA_TEST_S3_BUCKET');
        $key = getenv('RA_TEST_S3_KEY');
        $secret = getenv('RA_TEST_S3_SECRET');

        if ($endpoint === false || $bucket === false || $key === false || $secret === false) {
            self::markTestSkipped(
                'No object store configured. Set RA_TEST_S3_ENDPOINT, RA_TEST_S3_BUCKET, '
                . 'RA_TEST_S3_KEY and RA_TEST_S3_SECRET to run this against a real bucket.',
            );
        }

        $region = getenv('RA_TEST_S3_REGION');

        $this->storage = new S3Storage(
            HttpClients::best(),
            new SignatureV4($key, $secret, $region === false ? 'auto' : $region),
            $endpoint,
            $bucket,
        );

        $this->key = 'ra-test/' . bin2hex(random_bytes(8)) . '.txt';
    }

    protected function tearDown(): void
    {
        $this->storage?->delete($this->key);

        parent::tearDown();
    }

    public function testAFileGoesUpComesBackAndGoesAway(): void
    {
        $storage = $this->storage();
        $bytes = 'rockadmin ' . bin2hex(random_bytes(8));

        $storage->put($this->key, $bytes, 'text/plain');

        self::assertTrue($storage->exists($this->key));
        self::assertSame($bytes, $storage->get($this->key), 'The bytes came back changed.');

        $storage->delete($this->key);

        self::assertFalse($storage->exists($this->key));
    }

    public function testASignedUrlIsFetchableBySomethingThatIsNotThisAdmin(): void
    {
        // The claim the scripted tests cannot make: a URL this admin signed is
        // one the store itself accepts, from a client carrying no credentials.
        $storage = $this->storage();
        $bytes = 'signed ' . bin2hex(random_bytes(8));

        $storage->put($this->key, $bytes, 'text/plain');

        $response = HttpClients::best()->send('GET', $storage->url($this->key));

        self::assertSame(200, $response->status, 'The store refused a URL this admin signed.');
        self::assertSame($bytes, $response->body);
    }

    public function testAnUnsignedRequestForTheSameObjectIsRefused(): void
    {
        // The other half: the bucket is private, so the signature is what is
        // doing the work rather than the key being hard to guess.
        $storage = $this->storage();
        $storage->put($this->key, 'private', 'text/plain');

        $url = $storage->url($this->key);
        $withoutSignature = substr($url, 0, (int) strpos($url, '?'));

        $response = HttpClients::best()->send('GET', $withoutSignature);

        self::assertNotSame(200, $response->status, 'The bucket is public: this test proves nothing.');
    }

    public function testReadingSomethingThatIsNotThereSaysSo(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('no file stored as');

        $this->storage()->get('ra-test/' . bin2hex(random_bytes(8)) . '.txt');
    }

    private function storage(): S3Storage
    {
        return $this->storage ?? self::fail('No storage configured.');
    }
}
