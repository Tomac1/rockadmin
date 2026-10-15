<?php

declare(strict_types=1);

namespace RockAdmin\Form;

/**
 * What the files of one submission did, and what is owed afterwards.
 *
 * Three lists rather than one, because they are owed at three different
 * moments: the columns go into the write, the written keys are what to clean
 * up if that write fails, and the superseded ones are what to delete once it
 * has succeeded. A single "files that changed" would have to be re-derived at
 * each of the three, which is where one of them gets forgotten.
 */
final class FileWriteResult
{
    /**
     * @param array<string, mixed> $columns   column => a new key, or null where the file was removed
     * @param list<string>         $written   stored just now; delete these if the write does not happen
     * @param list<string>         $superseded what the row pointed at before; delete after the commit
     */
    public function __construct(
        public readonly array $columns = [],
        public readonly array $written = [],
        public readonly array $superseded = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->columns === [] && $this->written === [] && $this->superseded === [];
    }
}
