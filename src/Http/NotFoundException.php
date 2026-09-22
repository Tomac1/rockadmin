<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not found', ?Throwable $previous = null)
    {
        parent::__construct(404, $message, $previous);
    }
}
