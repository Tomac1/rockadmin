<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;
use Throwable;

/**
 * A `WriteHandler` that records what it was asked to do instead of doing it,
 * so a handler test can assert on the one write it made without a database.
 *
 * `$throw` is what makes the integrity-error path testable: an `ActionHandler`
 * must turn a SQLSTATE class 23 `DbException` into a form error and let
 * anything else propagate, and that is a decision about an exception's class
 * rather than about any particular database.
 */
final class RecordingWriteHandler implements WriteHandler
{
    /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> verb, key, values */
    public array $calls = [];

    public function __construct(
        private readonly ?Throwable $throw = null,
        private readonly string $generatedKey = '7',
    ) {
    }

    /** @param array<string, mixed> $values */
    public function insert(Entity $entity, array $values): WriteResult
    {
        $this->calls[] = ['insert', '', $values];
        $this->raise();

        return new WriteResult($this->generatedKey, [], $values);
    }

    /** @param array<string, mixed> $values */
    public function update(Entity $entity, string $key, array $values): WriteResult
    {
        $this->calls[] = ['update', $key, $values];
        $this->raise();

        return new WriteResult($key, $values, $values);
    }

    public function delete(Entity $entity, string $key): WriteResult
    {
        $this->calls[] = ['delete', $key, []];
        $this->raise();

        return new WriteResult($key, ['id' => $key], []);
    }

    /** @throws DbException|Throwable */
    private function raise(): void
    {
        if ($this->throw !== null) {
            throw $this->throw;
        }
    }
}
