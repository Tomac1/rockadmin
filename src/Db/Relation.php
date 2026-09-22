<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * A declared join.
 *
 * `$on` is raw SQL and is the one place in this layer where configuration
 * becomes SQL text rather than a bound value. That is deliberate — a join
 * condition cannot be expressed as a parameter — and it is safe only because
 * configuration lives in the project's repository. It must never be built
 * from anything that arrived in a request.
 */
final class Relation
{
    public function __construct(
        public readonly string $name,
        public readonly string $table,
        public readonly string $on,
        public readonly JoinType $type = JoinType::Left,
    ) {
    }
}
