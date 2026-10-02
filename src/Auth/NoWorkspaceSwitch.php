<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use Closure;

/**
 * The host that has nothing to switch: it runs the work and nothing else.
 *
 * What an admin with no `workspaces.php` gets, and what a project whose own
 * code does not care which workspace it is in gets. It exists so the handlers
 * have one shape rather than two -- a `?WorkspaceContext` tested for null at
 * every call site is a null test somebody eventually forgets, and the symptom
 * of forgetting it would be a write that ran under the wrong tenant.
 */
final class NoWorkspaceSwitch implements WorkspaceContext
{
    public function runIn(?WorkspaceDefinition $workspace, Closure $work): mixed
    {
        return $work();
    }
}
