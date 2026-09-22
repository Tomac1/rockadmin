<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use InvalidArgumentException;

/**
 * One configuration key, described well enough to generate its documentation.
 *
 * Every key the code reads has one of these, which is what makes an
 * undocumented option impossible: a key absent from the schema is rejected by
 * the validator, so a feature nobody wrote down cannot be used.
 */
final class SchemaKey
{
    public function __construct(
        public readonly ValueType $type,
        public readonly mixed $default = null,
        public readonly string $description = '',
        public readonly mixed $example = null,
        public readonly bool $required = false,
        public readonly bool $nullable = false,
        public readonly ?string $performance = null,
        public readonly ?Schema $children = null,
        public readonly ?Schema $each = null,
    ) {
        if ($children !== null && $each !== null) {
            throw new InvalidArgumentException(
                'A schema key describes either named children or a map of '
                . 'uniform entries, never both.',
            );
        }
    }
}
