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
}
