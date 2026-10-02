<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use Closure;
use RockAdmin\Auth\WorkspaceContext;
use RockAdmin\Auth\WorkspaceDefinition;

/**
 * A host that writes down what it was told instead of switching anything.
 *
 * It keeps `$active` the way a real one keeps its tenant — set on the way in,
 * restored in a `finally` — because the assertions are about what was active
 * *while* something ran, and only something that behaves like the real thing
 * can answer that.
 */
final class RecordingWorkspaceContext implements WorkspaceContext
{
    /** @var list<string> every workspace it was asked to switch to, in order */
    public array $seen = [];

    /** @var list<string> what the operations and their side effects reported */
    public array $reported = [];

    /** Whichever is active right now, which outside a `runIn` must be null. */
    public ?string $active = null;

    public function runIn(?WorkspaceDefinition $workspace, Closure $work): mixed
    {
        $before = $this->active;

        $this->active = $workspace?->name;
        $this->seen[] = $this->active ?? '(none)';

        try {
            return $work();
        } finally {
            $this->active = $before;
        }
    }
}
