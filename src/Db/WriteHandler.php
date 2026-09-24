<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * The one place a row changes.
 *
 * Inline editing, a form and a bulk action all end here — there is no second
 * way to write. A project may replace this wholesale (an ORM-backed
 * implementation is a documented recipe, kept out of the package so no ORM
 * enters the dependency list) or leave it as the default `SqlWriteHandler`.
 */
interface WriteHandler
{
    /** @param array<string, mixed> $values */
    public function insert(Entity $entity, array $values): WriteResult;

    /** @param array<string, mixed> $values */
    public function update(Entity $entity, string $key, array $values): WriteResult;

    public function delete(Entity $entity, string $key): WriteResult;
}
