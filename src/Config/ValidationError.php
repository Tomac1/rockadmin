<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/** One thing wrong with a configuration, and where. */
final class ValidationError
{
    public function __construct(
        public readonly string $path,
        public readonly string $message,
    ) {
    }
}
