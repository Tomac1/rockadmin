<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * What a route needs from the caller, when what it needs is not a permission.
 *
 * Three cases, and every one of them is a decision somebody made rather than
 * a gap somebody left. `Gate::ROUTES` maps a route either to a permission
 * pattern or to one of these, so "this route has no entry" is always a
 * mistake and never a policy.
 */
enum RouteAccess
{
    /**
     * Reachable by nobody in particular.
     *
     * The login form and the assets are the two the design spec names: without
     * them nothing renders and nothing styles the page it renders. Password
     * reset joins them because its entire purpose is to be reachable by
     * somebody who cannot sign in, and `_setup` because at first run there is
     * no user, no role and no `ra_users` table to hold either.
     *
     * A route here is outside the gate, so whatever protects it is its own
     * handler's job — a token for `_setup`, a signed token for a reset link.
     */
    case Anonymous;

    /**
     * Any signed-in user, and no permission beyond that.
     *
     * For routes that do not belong to a page and therefore cannot name a
     * `page.action`: the dashboard, logging out, switching workspace. Giving
     * them an invented permission name would be worse than this, because the
     * catalogue is generated from the pages and `Roles` denies anything not in
     * it — even to a role holding `*` — so the invented name would lock
     * everybody out of logging out.
     */
    case Authenticated;

    /**
     * Declared, and refused to everybody until somebody decides otherwise.
     *
     * The honest answer for a route whose access rule does not exist yet. It
     * keeps the route in the table — so the structural test passes and the
     * decision is visible — without inventing a permission that would quietly
     * grant more than intended.
     */
    case Denied;
}
