<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * Whichever client this installation can actually use.
 *
 * One place decides, so that `S3Storage` takes a client and never asks what
 * the server has installed -- and so that the answer is given once rather
 * than guessed at each call site.
 */
final class HttpClients
{
    /**
     * curl where it exists, the stream wrapper otherwise.
     *
     * curl is preferred for two reasons that both appear in production rather
     * than on a laptop: it does not need `allow_url_fopen`, which hardened
     * hosting turns off, and it tells a refused connection from a refused
     * request. The fallback exists because neither is guaranteed -- rule 1
     * keeps `composer.json` at four extensions, and `ext-curl` is not one of
     * them.
     */
    public static function best(int $timeoutSeconds = 30): HttpClient
    {
        return \extension_loaded('curl')
            ? new CurlHttpClient($timeoutSeconds)
            : new StreamHttpClient($timeoutSeconds);
    }
}
