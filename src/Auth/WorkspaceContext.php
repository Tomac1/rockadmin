<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use Closure;

/**
 * The host's own notion of "which workspace am I in", switched to match this
 * admin's.
 *
 * A multi-tenant project already has one. Bazik resolves a tenant from the
 * request's host and hands it to every repository it owns; a form field whose
 * options come from `CategoryRepository` gets the categories of whatever
 * tenant that service currently names. RockAdmin's own scope cannot help
 * there: the scope reaches the `WHERE` of the queries *RockAdmin* builds, and
 * the project's own code builds its own.
 *
 * So there are two ideas of the current workspace and they have to agree. The
 * admin's is the authority -- it is what the person switched to, and it is
 * what the scope is bound from -- and this is how the host is told.
 *
 * **It wraps rather than sets.** `switchTo()` followed by work followed by
 * `switchBack()` is three statements, and the day one of them is skipped is
 * the day a request leaks a tenant into whatever runs next -- a queued job, a
 * second page render, a mail template. A host implementing this gets its
 * `finally` written for it.
 *
 * Null means no particular workspace: the page is not divided by workspace at
 * all, or none are configured. A host is free to read that as its own default
 * -- it is "whatever you would have done without this", not "none".
 *
 * **This is the host's half of specification 9.3**, the mirror of
 * `SessionStore` and `Authenticator`: RockAdmin decides which workspace is
 * active and refuses rows outside it; the project decides what being in a
 * workspace means to its own code.
 */
interface WorkspaceContext
{
    /**
     * Runs $work with that workspace active, and restores whatever was active
     * before -- including when $work raises.
     *
     * @template T
     * @param  Closure():T $work
     * @return T
     */
    public function runIn(?WorkspaceDefinition $workspace, Closure $work): mixed;
}
