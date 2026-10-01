<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Auth\ActiveWorkspace;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\WorkspaceDefinition;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Http\WorkspaceHandler;

/**
 * What the switcher in the top bar offers this reader.
 *
 * `ShellAccount`'s sibling, and resolved per render for the same reason: a
 * handler is wired once and the reader belongs to the request.
 *
 * **It offers nothing when there is nothing to choose.** No workspaces
 * configured is the single-tenant admin; one workspace is somebody who is
 * simply in it, and a switcher with a single button is a control that cannot
 * do anything — the visible-button-that-does-nothing this project refuses.
 * Both draw no switcher at all.
 *
 * **"All workspaces" is offered only to somebody holding every one**, which
 * is specification 9.3's own condition: choosing it grants nothing they could
 * not already reach through the switcher, so it is a convenience rather than
 * a widening. For anybody else it would be a view across a boundary they
 * have, which is the one thing the boundary is for.
 */
final class ShellWorkspaces
{
    public function __construct(
        private readonly Workspaces $workspaces,
        private readonly ActiveWorkspace $active,
        private readonly Identity $identity,
        private readonly UrlGenerator $urls,
    ) {
    }

    /**
     * Where the switcher comes back to: the page being looked at.
     *
     * Here rather than on the handler because this is the object that already
     * holds a `UrlGenerator`, and a second one threaded through `PageHandler`
     * purely to build one string would be a dependency added for a sentence.
     */
    public function pageUrl(string $page): string
    {
        return $this->urls->route('page.index', ['page' => $page]);
    }

    /**
     * The switcher's entries, or none.
     *
     * @return list<WorkspaceView>
     */
    public function all(): array
    {
        $user = $this->identity->user();
        $theirs = $this->workspaces->forUser($user);

        if (\count($theirs) < 2) {
            return [];
        }

        $current = $this->active->current($user);

        $views = array_map(
            fn (WorkspaceDefinition $workspace): WorkspaceView => new WorkspaceView(
                $workspace->name,
                $workspace->label,
                $this->urls->route('workspace.switch', ['workspace' => $workspace->name]),
                $current?->name === $workspace->name,
            ),
            $theirs,
        );

        if ($this->workspaces->userHoldsEvery($user)) {
            $views[] = new WorkspaceView(
                WorkspaceHandler::ALL,
                'All workspaces',
                $this->urls->route('workspace.switch', ['workspace' => WorkspaceHandler::ALL]),
                $current === null,
            );
        }

        return $views;
    }
}
