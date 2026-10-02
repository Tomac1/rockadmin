<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Http\SessionStore;

/**
 * The active workspace, kept in the session and switched from the navbar.
 *
 * Specification 9.3's own arrangement, and the default. It owns its session
 * key for the reason `Identity` owns the one that says who is signed in:
 * exactly one string in this project says which workspace is active, because
 * two copies of a session key is how switching stops switching.
 *
 * **Nothing here is trusted as it stands.** A session is editable by whoever
 * holds it, so the name in it is resolved against the configured workspaces
 * *and* against what this user holds, on every request. A name that is no
 * longer either is ignored rather than honoured — access is revoked by
 * changing the user, and a stale session must not outlive that.
 *
 * **One workspace is not a choice.** Somebody who holds exactly one is in it,
 * with nothing in the session and nothing to get wrong; the switcher has
 * nothing to offer and does not appear. That also means the common case —
 * most people in a multi-tenant admin work in one tenant — costs no session
 * writes at all.
 */
final class SessionWorkspace implements ActiveWorkspace
{
    /**
     * Namespaced with `ra.` for the reason `Identity`'s key is: a host
     * sharing its own session array with this admin must not find its own
     * `workspace` overwritten.
     */
    private const string KEY = 'ra.workspace';

    /**
     * The sentinel the session holds for "all workspaces".
     *
     * A workspace name may not contain a dot — `Workspaces` refuses anything
     * that is not lower-case snake_case, because the name is a URL segment —
     * so this cannot collide with a real one.
     */
    private const string ALL = 'ra.all';

    /** @var array{set: bool, workspace: ?WorkspaceDefinition} */
    private array $resolved = ['set' => false, 'workspace' => null];

    public function __construct(
        private readonly SessionStore $session,
        private readonly Workspaces $workspaces,
    ) {
    }

    public function current(?AuthUser $user): ?WorkspaceDefinition
    {
        if ($this->resolved['set']) {
            return $this->resolved['workspace'];
        }

        $this->resolved = ['set' => true, 'workspace' => $this->resolve($user)];

        return $this->resolved['workspace'];
    }

    public function isAutomatic(?AuthUser $user): bool
    {
        $theirs = $this->workspaces->forUser($user);

        if (\count($theirs) < 2) {
            // Nothing to choose, so nothing can have been chosen. Answering
            // "chosen" here would make the switcher mark a workspace as a
            // decision somebody made, when in fact it is the only one they
            // hold.
            return true;
        }

        $stored = $this->session->get(self::KEY);

        if ($stored === self::ALL) {
            // A deliberate choice, until it is not theirs any more -- at which
            // point `resolve()` falls back to the first workspace they hold,
            // and so does this. The two have to agree about what the session
            // means or the switcher marks something `current()` does not
            // answer.
            return !$this->workspaces->userHoldsEvery($user);
        }

        return !\is_string($stored)
            || !$this->workspaces->has($stored)
            || $user?->hasWorkspace($stored) !== true;
    }

    public function automatic(): void
    {
        $this->session->forget(self::KEY);

        // The memo is replaced rather than cleared, for the reason
        // `switchTo()` gives: a request that switches goes on to redirect, and
        // anything asking afterwards should see the new answer.
        $this->resolved = ['set' => false, 'workspace' => null];
    }

    public function switchTo(?WorkspaceDefinition $workspace): void
    {
        $this->session->set(self::KEY, $workspace === null ? self::ALL : $workspace->name);

        // The memo is replaced rather than cleared: a switch happens during a
        // request that goes on to redirect, and anything asking afterwards
        // should see what was just chosen rather than re-reading a session
        // the response has not written yet.
        $this->resolved = ['set' => true, 'workspace' => $workspace];
    }

    private function resolve(?AuthUser $user): ?WorkspaceDefinition
    {
        $theirs = $this->workspaces->forUser($user);

        if ($theirs === []) {
            // Either no workspaces are configured, in which case nothing is
            // scoped and null is right, or this user holds none, in which
            // case they can see nothing and `Kernel` says so. Both are null
            // here; telling them apart is not this class's job, and doing it
            // here would mean two places deciding the same thing.
            return null;
        }

        if (\count($theirs) === 1) {
            // No choice to store and none to get wrong.
            return $theirs[0];
        }

        $stored = $this->session->get(self::KEY);

        if ($stored === self::ALL) {
            // Offered only to somebody holding every workspace, and checked
            // again here rather than trusted: a session saying "all" written
            // before a workspace was taken away must not survive it.
            return $this->workspaces->userHoldsEvery($user) ? null : $theirs[0];
        }

        if (\is_string($stored) && $this->workspaces->has($stored) && $user?->hasWorkspace($stored) === true) {
            return $this->workspaces->get($stored);
        }

        // Nothing stored, or something stale. The first workspace they hold,
        // in the order the file declares them -- a deterministic answer
        // rather than whichever the database happened to return first.
        return $theirs[0];
    }
}
