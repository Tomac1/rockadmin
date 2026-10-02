<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * Which workspace this request is in.
 *
 * A seam, like `SessionStore` and `Authenticator`, and it exists because the
 * two obvious answers are both right for somebody.
 *
 * Specification 9.3 puts the active workspace in the session and switches it
 * from the navbar, which is what an admin serving several tenants wants: one
 * person, several contexts, one at a time. `SessionWorkspace` is that, and it
 * is the default.
 *
 * But a host may already know. `bazik.cz-redesign` resolves its tenant per
 * request from the `Host` header, in its own `TenantService`, and an admin
 * mounted on `admin.cyklobazar.cz` has nothing to switch — the domain decided
 * before the request reached PHP. Such a host implements this and returns the
 * one workspace, and the switcher does not appear because there is nothing to
 * switch to.
 *
 * **Null means "all workspaces"**, not "none decided". It is reachable only
 * for somebody holding every workspace (9.3), and it means the scope is
 * omitted — which is why it is a deliberate value rather than an absence. A
 * user who holds no workspace at all is not this interface's problem: they
 * cannot see anything, and `Kernel` says so rather than letting a null fall
 * through into an unscoped query.
 *
 * **There are three states, not two**, and the third is why this interface
 * has four methods:
 *
 * - **a named workspace** — somebody chose it, or the host knows it;
 * - **all of them** — somebody chose that, and the scope is omitted;
 * - **automatic** — nobody has chosen anything, so the answer is whatever
 *   this implementation decides for itself: the first workspace the user
 *   holds, or, for a host like `bazik.cz-redesign`, whatever its own resolver
 *   makes of the request.
 *
 * Automatic is not the same as either of the others, even when it currently
 * answers the same workspace. A choice outlives the request that made it and
 * an automatic answer does not, so an admin mounted on one address for several
 * tenants behaves differently under the two: automatic follows the host, a
 * choice overrides it. Somebody who has switched must be able to get back —
 * `automatic()` is that, and it exists because "undo my choice" cannot be
 * expressed by choosing something.
 */
interface ActiveWorkspace
{
    /**
     * The workspace this request is in, or null for all of them.
     *
     * Asked more than once per request — the kernel checks the page, the
     * query binds the scope, the shell draws the switcher — so an
     * implementation that does real work memoises it. The answer cannot
     * change within one request.
     */
    public function current(?AuthUser $user): ?WorkspaceDefinition;

    /**
     * Makes this workspace the active one, or all of them when null.
     *
     * Whoever calls it has already checked that the user may: this records a
     * decision rather than making one. `WorkspaceHandler` is the only caller
     * in the SDK, and it refuses a workspace the user does not hold before
     * getting here.
     */
    public function switchTo(?WorkspaceDefinition $workspace): void;

    /**
     * Forgets whatever was chosen: back to whatever this implementation would
     * have answered on its own.
     *
     * Needed because a choice is sticky and the default is not reachable by
     * choosing it. Somebody who switched to Motobazar.cz on an admin that
     * follows the request's host has no way back to "follow the host" unless
     * this exists -- and they cannot work out which workspace the host would
     * have chosen, because that is the implementation's business and not
     * theirs.
     *
     * Always allowed. It cannot widen anything: what follows is this
     * implementation's own answer, which is what they would have got before
     * they ever touched the switcher.
     */
    public function automatic(): void;

    /**
     * Whether nothing has been chosen, so `current()` is this
     * implementation's own answer.
     *
     * Read by the switcher, which has to show which of the three states is
     * the current one. The user is passed for the same reason `current()`
     * takes one: an implementation may answer differently for somebody
     * holding one workspace, where there is nothing to choose and therefore
     * nothing to have chosen.
     */
    public function isAutomatic(?AuthUser $user): bool;
}
