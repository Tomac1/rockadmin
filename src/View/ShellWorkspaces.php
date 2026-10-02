<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Auth\ActiveWorkspace;
use RockAdmin\Auth\Identity;
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
 * **"Automatic" is offered whenever the switcher is**, because a choice is
 * sticky: somebody who switched to Motobazar.cz cannot get back to "whatever
 * this admin would have picked" by picking something, and on an installation
 * that resolves its workspace from the request's host, that is the difference
 * between following the address and overriding it. The entry says what it
 * currently resolves to, so it is a readable answer rather than a mode nobody
 * can see the effect of.
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
        $automatic = $this->active->isAutomatic($user);

        // Exactly one entry is marked, and which one is the state rather than
        // the answer. Under automatic the workspace it currently resolves to
        // is *not* marked: a switcher showing two current entries would be
        // asking somebody to work out which of them they are in.
        $views = [new WorkspaceView(
            WorkspaceHandler::AUTOMATIC,
            $current === null
                ? 'Automatic'
                : "Automatic ({$current->label})",
            $this->urls->route('workspace.switch', ['workspace' => WorkspaceHandler::AUTOMATIC]),
            $automatic,
        )];

        foreach ($theirs as $workspace) {
            $views[] = new WorkspaceView(
                $workspace->name,
                $workspace->label,
                $this->urls->route('workspace.switch', ['workspace' => $workspace->name]),
                !$automatic && $current?->name === $workspace->name,
            );
        }

        if ($this->workspaces->userHoldsEvery($user)) {
            $views[] = new WorkspaceView(
                WorkspaceHandler::ALL,
                'All workspaces',
                $this->urls->route('workspace.switch', ['workspace' => WorkspaceHandler::ALL]),
                !$automatic && $current === null,
            );
        }

        return $views;
    }
}
