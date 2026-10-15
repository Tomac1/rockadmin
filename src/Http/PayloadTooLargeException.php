<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

/**
 * The request was too large for this server, so none of it arrived.
 *
 * A 413, and the only status in this admin that is answered before the CSRF
 * token is looked at -- because the token is part of what went missing. PHP
 * discards the body of a POST over `post_max_size` before any application
 * code runs: `$_POST` is empty, `$_FILES` is empty, nothing is raised, and
 * the request arrives looking exactly like a form submitted with no fields
 * at all.
 *
 * Without this it is answered as a failed token, which is a lie that costs a
 * support ticket: somebody uploads a large photograph, is told the form went
 * stale, signs in again, uploads the same photograph, and is told the same
 * thing.
 */
final class PayloadTooLargeException extends HttpException
{
    public function __construct(string $message = 'Payload too large', ?Throwable $previous = null)
    {
        parent::__construct(413, $message, $previous);
    }
}
