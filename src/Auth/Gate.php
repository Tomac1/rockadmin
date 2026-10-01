<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Config\Config;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\Route;
use RockAdmin\Page\PageRepository;

/**
 * Step 7: may this user do this.
 *
 * One `allows()`, and two callers. The kernel asks before a handler runs, so
 * the check cannot be forgotten for an AJAX endpoint — specification 5.2 calls
 * that historically the most common hole in admin tools, and this project's
 * whole routing design exists to make it structural. Rendering asks the same
 * method, so a menu item or a button the gate would refuse is never drawn
 * (9.2): a visible control that returns 403 is a bug, not a safeguard. There
 * is deliberately no second rule for what rendering may show, because two
 * copies of a rule drift until a button appears that the gate refuses.
 *
 * **Every route declares what it needs, in one table.** `ROUTES` covers every
 * name `Router` matches, and `GateTest` asserts that over the router's own
 * data, so a route added later without a decision fails the suite rather than
 * shipping open. A route with no entry is refused rather than allowed through.
 *
 * **It fails closed, everywhere.** An undeclared route, an action verb nobody
 * mapped to a permission, a permission the pages never generated: each is a
 * denial. A page brings its own permissions with it, so the only ways to reach
 * an undeclared one are a bug and a hand-written URL, and both should be
 * refused rather than guessed at.
 *
 * **One list of exceptions, not a flag per handler.** The routes an anonymous
 * caller may reach are `RouteAccess::Anonymous` entries in this same table —
 * so the redirect at step 4 and the permission check at step 7 read one
 * source. Two lists for one fact is how the gate and the redirect end up
 * disagreeing about `/login`.
 */
final class Gate
{
    /**
     * `{page}` is replaced by the route's page parameter.
     *
     * `{action}` is replaced by the permission the route's action verb maps to
     * in `ACTION_PERMISSIONS`, which is a closed set: a verb that is not in it
     * is refused, not passed through. Without that, `POST /a/ads/publish`
     * would ask for a permission named `ads.publish`, and the day somebody
     * adds a `publish` action it would silently start being granted by
     * `ads.*`.
     *
     * @var array<string, string|RouteAccess>
     */
    private const array ROUTES = [
        // Reachable without signing in. The two the specification names are
        // `/login` and `/_assets`; the rest are routes whose entire purpose is
        // to work for somebody who cannot sign in, and each of them owes its
        // own handler a check the gate will not do.
        'login.form' => RouteAccess::Anonymous,
        'login.submit' => RouteAccess::Anonymous,
        'assets' => RouteAccess::Anonymous,

        // Password reset: unhandled until `Mailer` exists. Anonymous is not a
        // convenience here, it is the requirement — a person who has forgotten
        // their password cannot sign in first. Whoever writes the handler owes
        // it a single-use, expiring token out of `ra_password_resets`, and the
        // same throttling `SqlAuthenticator` applies to attempts, because this
        // route is an email-enumeration oracle otherwise.
        'password.request.form' => RouteAccess::Anonymous,
        'password.request.submit' => RouteAccess::Anonymous,
        'password.reset.form' => RouteAccess::Anonymous,
        'password.reset.submit' => RouteAccess::Anonymous,

        // First-run setup: unhandled. It cannot require a permission, because
        // at first run there is no user to hold one and possibly no `ra_users`
        // table to hold the user — this is the route somebody recovers a
        // broken installation through, and a gate in front of it would lock
        // the door from the inside. Specification 11.4 makes it token-gated
        // instead, and that token is the handler's responsibility.
        'setup.form' => RouteAccess::Anonymous,
        'setup.submit' => RouteAccess::Anonymous,

        // Signed in, and nothing more. None of these belongs to a page, so
        // none of them can name a `page.action` that the catalogue would
        // contain.
        'dashboard' => RouteAccess::Authenticated,
        'logout' => RouteAccess::Authenticated,

        // Switching workspace: unhandled. Any signed-in user may ask; whether
        // this particular workspace is theirs is `AuthUser::hasWorkspace()`,
        // and the scope check that goes with it is the next milestone's. That
        // check belongs in the handler, not here, because it is about a value
        // in the URL rather than about the route.
        'workspace.switch' => RouteAccess::Authenticated,

        // Diagnostics: unhandled, and refused. Specification 5.1 says it needs
        // `dev.console`, which is not a `page.action` and so cannot exist in a
        // catalogue generated from the pages. It prints the environment, so
        // the wrong default is the permissive one; `_setup` is the recovery
        // path, not this. Whoever builds the `dev.console` concept changes
        // this line and has to think about it to do so.
        'diagnostics' => RouteAccess::Denied,

        // The pages.
        'page.index' => '{page}.view',
        'page.detail' => '{page}.view',
        'region' => '{page}.view',
        'page.create' => '{page}.create',

        'page.edit' => '{page}.update',

        // Copy prefills a form from an existing row and saves it as a NEW one:
        // its submit posts to `a/{page}/create`. Asking for `{page}.update` to
        // open it meant somebody with update and not create could reach a form
        // whose only button answers 403 -- the visible-button-that-403s this
        // whole task exists to remove, arriving through the permission table
        // rather than through the templates. So it asks for the permission its
        // own save needs.
        //
        // The narrower reading, that copying also reads the source row and so
        // should want `{page}.view` too, is already covered: nothing reaches a
        // copy form without a link or a typed URL, and both come from a page
        // the viewer had to be allowed to open.
        'page.copy' => '{page}.create',

        'action' => '{page}.{action}',
    ];

    /**
     * The verbs `POST /a/{page}/{action}` accepts, and what each one needs.
     *
     * A closed set for the reason above. `ActionHandler::VERBS` is the same
     * three; when milestone 8 adds bulk actions and inline editing it adds
     * them here too, or they are refused — which is the correct failure.
     *
     * @var array<string, string>
     */
    private const array ACTION_PERMISSIONS = [
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
    ];

    public function __construct(private readonly Roles $roles)
    {
    }

    /**
     * Builds the roles eagerly, which is the only thing that validates them.
     *
     * `Loader` reads `roles.php` and stores it raw — it has no page
     * repository, and giving it one would have the configuration layer reach
     * into the page layer to build something it does not itself use. So the
     * refusals in `Roles::fromConfig()` only fire when somebody calls it, and
     * if nobody does, a `roles.php` full of typos is never read at all. This
     * is that caller, and a host wires it before it handles anything, so a
     * broken file fails the request instead of sitting there quietly granting
     * nothing.
     */
    public static function fromConfig(Config $config, PageRepository $pages): self
    {
        $catalogue = Permission::catalogue($pages);

        // An action's declared permission (spec 8.5) is refused in the same
        // breath, and for the same reason: a typo in it would deny that action
        // forever, to everybody, with nothing anywhere saying why. The page
        // layer cannot check it — the catalogue is generated from every page
        // by this namespace, and `Page` depending back on `Auth` is a cycle.
        Permission::assertActionsAreGrantable($pages, $catalogue);

        return new self(Roles::fromConfig($config->roles(), $catalogue));
    }

    /** Whether this route has an access decision at all. */
    public static function declares(string $routeName): bool
    {
        return \array_key_exists($routeName, self::ROUTES);
    }

    /**
     * Every route this table decides, so a test can compare it against the
     * router's own names in both directions.
     *
     * @return list<string>
     */
    public static function routeNames(): array
    {
        return array_keys(self::ROUTES);
    }

    /**
     * What this route needs, before any user is involved.
     *
     * @throws ForbiddenException when nobody decided
     */
    public static function accessFor(string $routeName): string|RouteAccess
    {
        return self::ROUTES[$routeName] ?? throw new ForbiddenException(
            "Route '{$routeName}' declares no permission and is not an exception, so it is refused. "
            . 'Add it to Gate::ROUTES.',
        );
    }

    /**
     * The permission this particular request needs, or null when the route
     * needs only a signed-in user.
     *
     * @throws ForbiddenException for an undeclared route, a denied one, or an
     *                            action verb nobody mapped to a permission
     */
    public static function permissionFor(Route $route): ?string
    {
        $access = self::accessFor($route->name);

        if ($access === RouteAccess::Denied) {
            throw new ForbiddenException(
                "Route '{$route->name}' is declared as refused to everybody.",
            );
        }

        if ($access instanceof RouteAccess) {
            return null;
        }

        $permission = str_replace('{page}', $route->param('page'), $access);

        if (!str_contains($permission, '{action}')) {
            return $permission;
        }

        $verb = $route->param('action');
        $mapped = self::ACTION_PERMISSIONS[$verb] ?? throw new ForbiddenException(
            "No permission is declared for the action '{$verb}', so it is refused. The actions this "
            . "admin grants are '" . implode("', '", array_keys(self::ACTION_PERMISSIONS)) . "'.",
        );

        return str_replace('{action}', $mapped, $permission);
    }

    /**
     * Whether this user carries this permission.
     *
     * Null is nobody, and nobody is allowed nothing — so rendering may call
     * this with an unresolved identity without a null check of its own.
     */
    public function allows(?AuthUser $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->roles->allows($user->roles, $permission);
    }

    /**
     * The kernel's call: let this request through, or refuse it.
     *
     * The message names the permission. That is a deliberate leak in one
     * direction only — it is shown to somebody who is already signed in, about
     * a page whose existence they could establish by other means, and being
     * told which permission you lack is the difference between a support
     * ticket somebody can answer and one they cannot.
     *
     * @throws ForbiddenException
     */
    public function authorize(AuthUser $user, Route $route): void
    {
        $permission = self::permissionFor($route);

        if ($permission === null || $this->allows($user, $permission)) {
            return;
        }

        throw new ForbiddenException(
            "You do not have permission '{$permission}'.",
        );
    }
}
