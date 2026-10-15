<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

use RockAdmin\Http\HttpClient;
use RockAdmin\Http\HttpClientException;

/**
 * An object store that speaks S3, which is most of them.
 *
 * Cloudflare R2, MinIO on a laptop, Backblaze B2, DigitalOcean Spaces, Wasabi
 * and Amazon itself all answer the same four verbs with the same signature, so
 * this is one class rather than one per vendor — and S3 is named here as a
 * protocol, the way `SmtpMailer` is named after SMTP and not after a mail
 * provider.
 *
 * **R2 is an endpoint, a bucket and a region of `auto`.** Nothing in this file
 * knows about Cloudflare; what makes it work against R2 is three values in
 * `rockadmin.php`.
 *
 * **Path-style addressing**, `https://endpoint/bucket/key`, rather than
 * `https://bucket.endpoint/key`. R2's account endpoint requires it, MinIO
 * defaults to it, and AWS still accepts it — one shape that works everywhere
 * beats a flag nobody knows how to set.
 *
 * **Reading is a signed URL, not a download.** `url()` signs a GET that is
 * good for a few minutes and hands the browser the address: the bytes go from
 * the store to the person without passing through PHP, which is the entire
 * reason to use an object store. `get()` exists for the admin's own use —
 * copying, checking — and is the slow path on purpose.
 *
 * **No dependency, and no AWS SDK.** `SignatureV4` is the algorithm and
 * `HttpClient` is the socket; rule 1 holds, and a project that would rather
 * use its own client or its own storage wires either one in.
 */
final class S3Storage implements Storage
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly SignatureV4 $signer,
        /**
         * The API endpoint: scheme and host, and no path.
         *
         * R2 also publishes a bucket-scoped form of the same address, ending
         * in the bucket name, and putting that here is the easy mistake --
         * easy because it *works*: the bucket is appended again, every verb
         * agrees with every other, and the files land one directory deeper
         * than anybody will look for them. It is refused for that reason.
         */
        private readonly string $endpoint,
        private readonly string $bucket,
        /**
         * How long a signed link stays good for.
         *
         * Long enough to open a page and look at what is on it, short enough
         * that a URL copied out of a browser history tomorrow is worth
         * nothing. It is why `url()` is called at render time and its answer
         * is never stored.
         */
        private readonly int $linkSeconds = 600,
        /**
         * A public base URL, for a bucket that is deliberately public.
         *
         * Set, it wins and nothing is signed: the files behind it are
         * readable by anybody who learns the address, which is right for
         * product photos and wrong for everything an admin usually holds. The
         * default is empty, and the default is private.
         */
        private readonly string $publicBaseUrl = '',
    ) {
        $path = parse_url($endpoint, PHP_URL_PATH);

        if (\is_string($path) && trim($path, '/') !== '') {
            throw new StorageException(\sprintf(
                "storage.endpoint is '%s', which has a path. The bucket goes in storage.bucket and "
                . 'nowhere else -- left here it is appended twice, which works and quietly stores '
                . "everything under '%s/%s/'. Drop the path.",
                $endpoint,
                trim($path, '/'),
                $this->bucket,
            ));
        }
    }

    public function put(string $key, string $contents, string $contentType): void
    {
        $response = $this->send('PUT', $key, [
            'content-type' => $contentType,
            'content-length' => (string) \strlen($contents),
        ], $contents);

        if (!$response->succeeded()) {
            throw new StorageException($this->refusal('store', $key, $response->status, $response->body));
        }
    }

    public function get(string $key): string
    {
        $response = $this->send('GET', $key);

        if ($response->status === 404) {
            throw new StorageException("There is no file stored as '{$key}'.");
        }

        if (!$response->succeeded()) {
            throw new StorageException($this->refusal('read', $key, $response->status, $response->body));
        }

        return $response->body;
    }

    public function delete(string $key): void
    {
        $response = $this->send('DELETE', $key);

        // 204 is the usual answer and 404 means somebody else got there
        // first, which the interface says is not a failure.
        if ($response->succeeded() || $response->status === 404) {
            return;
        }

        throw new StorageException($this->refusal('remove', $key, $response->status, $response->body));
    }

    public function exists(string $key): bool
    {
        if (!StorageKey::isValid($key)) {
            return false;
        }

        return $this->send('HEAD', $key)->succeeded();
    }

    public function url(string $key): string
    {
        StorageKey::require($key);

        if ($this->publicBaseUrl !== '') {
            return rtrim($this->publicBaseUrl, '/') . '/' . $key;
        }

        return $this->signer->presignedUrl('GET', $this->urlFor($key), $this->linkSeconds);
    }

    public function accepts(string $key): bool
    {
        return StorageKey::isValid($key);
    }

    /**
     * One signed request.
     *
     * The payload hash is of the body actually sent, which is what
     * `x-amz-content-sha256` promises and what the store checks: a hash of
     * anything else is a signature mismatch, which is to say a 403 that says
     * nothing about hashes.
     *
     * @param array<string, string> $headers
     */
    private function send(
        string $method,
        string $key,
        array $headers = [],
        string $body = '',
    ): \RockAdmin\Http\HttpResponse {
        $url = $this->urlFor(StorageKey::require($key));

        $signed = $this->signer->headersFor(
            $method,
            $url,
            $headers,
            $body === '' ? SignatureV4::EMPTY_PAYLOAD : hash('sha256', $body),
        );

        try {
            return $this->http->send($method, $url, $signed, $body);
        } catch (HttpClientException $e) {
            throw new StorageException(\sprintf(
                "The object store could not be reached while trying to %s '%s': %s",
                strtolower($method),
                $key,
                $e->getMessage(),
            ), previous: $e);
        }
    }

    private function urlFor(string $key): string
    {
        return rtrim($this->endpoint, '/') . '/' . trim($this->bucket, '/') . '/' . $key;
    }

    /**
     * What the store said no to, in words that name the likely cause.
     *
     * The status is the useful half -- 403 is credentials or a clock, 404 is a
     * bucket that is not there under that name -- and the store's own XML is
     * quoted short, because it names the bucket and the request id a
     * postmaster would ask for and nothing secret.
     */
    private function refusal(string $verb, string $key, int $status, string $body): string
    {
        $hint = match (true) {
            $status === 403 => ' A 403 here is the access key, the secret, the bucket name or a '
                . 'server clock more than fifteen minutes out -- the store will not say which.',
            $status === 404 => ' A 404 on a write usually means the bucket name is wrong.',
            $status >= 500 => ' That is the store failing rather than this admin.',
            default => '',
        };

        return \sprintf(
            "The object store refused to %s '%s' (HTTP %d).%s%s",
            $verb,
            $key,
            $status,
            $hint,
            $body === '' ? '' : ' It said: ' . mb_substr(trim(strip_tags($body)), 0, 200),
        );
    }
}
