<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RuntimeException;

/**
 * The request did not happen: no route to the host, TLS refused, the socket
 * timed out.
 *
 * A reply that says no is not this. A 403 from an object store is an answer,
 * and the caller is in a better position to say what it meant -- "the bucket
 * refused these credentials" is a sentence `S3Storage` can write and this
 * class cannot.
 */
final class HttpClientException extends RuntimeException
{
}
