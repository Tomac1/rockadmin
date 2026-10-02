<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Auth\ActiveWorkspace;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Form\FormFields;

/**
 * `POST /w/{workspace}` — switching which workspace you are working in.
 *
 * **A POST, and specification 5.1 gives the reason**: browsers prefetch links
 * on hover, so a GET would let a hover over the switcher quietly change what
 * somebody is looking at. It changes what the session shows for the rest of
 * its life, which is exactly the kind of thing a link scanner must not be
 * able to do by existing.
 *
 * The three refusals here are all the same refusal seen from different
 * sides: you may switch to a workspace you hold, and to nothing else. The
 * switcher does not offer the others, and this does not trust the switcher —
 * the URL is typeable and the body is editable.
 */
final class WorkspaceHandler implements Handler
{
    /**
     * The name that means "all workspaces".
     *
     * A leading underscore, because `Workspaces` refuses it in a real name:
     * a workspace is `^[a-z][a-z0-9_]*$`, so `_all` cannot collide with one
     * however an installation names its tenants. A sentinel that *could* be
     * a real name would make "all workspaces" unreachable for whoever named
     * a workspace `all`, and nobody would ever work out why.
     */
    public const string ALL = '_all';

    /**
     * The name that means "whatever you would have chosen for me".
     *
     * The third state, and the switcher's only way back to it: a choice is
     * sticky, and nobody can un-choose by choosing. On an admin that resolves
     * its workspace from the request -- one address, several tenants -- this
     * is the difference between following the address and overriding it, so it
     * is not a convenience.
     *
     * Underscored like `ALL`, and for the same reason: `Workspaces` refuses a
     * name that is not `^[a-z][a-z0-9_]*$`, so this cannot collide with an
     * installation that names a tenant `auto`.
     */
    public const string AUTOMATIC = '_auto';

    public function __construct(
        private readonly Workspaces $workspaces,
        private readonly ActiveWorkspace $active,
        private readonly Identity $identity,
        private readonly Csrf $csrf,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $token = $request->input(Csrf::FIELD);

        if (!\is_string($token) || !$this->csrf->isValid($token)) {
            throw new ForbiddenException(
                'This request carried no valid CSRF token, so it is refused. Switching workspace '
                . 'changes what you see for the rest of the session.',
            );
        }

        $user = $this->identity->user();

        if ($user === null) {
            throw new ForbiddenException('Nobody is signed in, so there is no workspace to switch.');
        }

        $name = $route->param('workspace');

        if ($name === self::ALL) {
            if (!$this->workspaces->userHoldsEvery($user)) {
                // 9.3 offers "all workspaces" only to somebody who could
                // reach every row through the switcher anyway. For anyone
                // else it would be a view across a boundary they have, which
                // is the one thing the boundary is for.
                throw new ForbiddenException(
                    'Working across all workspaces needs access to all of them.',
                );
            }

            $this->active->switchTo(null);

            return $this->back($request);
        }

        if ($name === self::AUTOMATIC) {
            // No check, because there is nothing to check. What follows is
            // whatever the resolver would have answered before anybody
            // touched the switcher, which is by definition something this
            // user was already allowed to see.
            $this->active->automatic();

            return $this->back($request);
        }

        if (!$this->workspaces->has($name) || !$user->hasWorkspace($name)) {
            // One refusal for both, deliberately. Telling somebody that a
            // workspace exists but is not theirs is telling them what their
            // employer's other tenants are called.
            throw new ForbiddenException("You do not have access to the workspace '{$name}'.");
        }

        $this->active->switchTo($this->workspaces->get($name));

        return $this->back($request);
    }

    /**
     * Back where they were, or the dashboard.
     *
     * The referer is validated as an internal path by the same rule a form's
     * `_ret` is, because it arrives from the browser and is no more
     * trustworthy here than it is there. Switching to the page you were on is
     * the useful behaviour -- the grid reloads in the new workspace -- and
     * the dashboard is the safe fallback, since the page you were on may not
     * be in the workspace you have just moved to.
     */
    private function back(Request $request): Response
    {
        $to = FormHandler::returnTo($request->input(FormFields::RETURN_TO), $this->urls);

        return Response::redirect($to ?? $this->urls->route('dashboard'));
    }
}
