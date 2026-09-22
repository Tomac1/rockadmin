<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RuntimeException;
use Throwable;

/** An exception that already knows which HTTP status it should produce. */
class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
