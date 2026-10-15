<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * One HTTP request to somewhere that is not this admin.
 *
 * It exists for the same reason `SmtpTransport` does: so that the thing above
 * it can be tested. `S3Storage` is a signature and five verbs, and what goes
 * wrong with it -- a header in the wrong order, a payload hash of the wrong
 * bytes, a 403 that means the clock is skewed -- is provable against a
 * scripted client in a millisecond and otherwise only against a bucket.
 *
 * It is also the seam a host uses to hand over its own client: a project with
 * Guzzle already configured, with its own proxy, its own timeouts and its own
 * retry policy, implements this in a dozen lines and the shipped ones never
 * run.
 *
 * Deliberately small. No redirects, no cookies, no streaming: an object store
 * does not redirect, has no session, and an admin upload fits in memory.
 */
interface HttpClient
{
    /**
     * @param  array<string, string> $headers
     * @throws HttpClientException   when there was no reply at all, which is
     *                               different from a reply that says no
     */
    public function send(string $method, string $url, array $headers = [], string $body = ''): HttpResponse;
}
