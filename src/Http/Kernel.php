<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\RouteAccess;
use Throwable;

/**
 * Match, check, dispatch, catch.
 *
 * The kernel knows nothing about pages, configuration or the database. It does
 * know who is asking and whether they are allowed, because those are the two
 * questions that must be answered for every route alike: a check a handler
 * performs is a check some handler will eventually not perform, and the AJAX
 * endpoint is always the one that gets missed (design spec 5.2).
 *
 * **Identity and the gate are constructor arguments, not optional ones.**
 * There is no way to build a kernel that skips them, because "the gate is not
 * optional" is only true if nothing can be wired without it.
 *
 * **Routing runs before identity**, where the numbered lifecycle in 5.2 puts
 * it after. The order there is written for a redirect keyed on a path; the
 * exceptions here are keyed on route *names*, in one table with the permission
 * rules, so the route has to be known first. Matching is a pure lookup over a
 * constant — no session, no query, no side effect — so moving it earlier
 * changes nothing except that steps 4 and 7 read the same list.
 */
final class Kernel
{
    public function __construct(
        private readonly Router $router,
        private readonly HandlerRegistry $handlers,
        private readonly ErrorHandler $errors,
        private readonly Identity $identity,
        private readonly Gate $gate,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->match($request)
                ?? throw new NotFoundException("No route matches {$request->method} /{$request->path}");

            if (Gate::accessFor($route->name) !== RouteAccess::Anonymous) {
                $user = $this->identity->user();

                if ($user === null) {
                    return $this->unauthenticated($route, $request);
                }

                $this->gate->authorize($user, $route);
            }

            // The handler is looked up *after* the gate, not before. A 404 for
            // a route that exists but has no handler is a fact about this
            // installation, and an unauthorised caller does not get to learn
            // facts about this installation by watching which refusal comes
            // back.
            $handler = $this->handlers->find($route->name)
                ?? throw new NotFoundException("No handler registered for route {$route->name}");

            return $handler->handle($route, $request);
        } catch (Throwable $e) {
            return $this->errors->toResponse($e);
        }
    }

    /**
     * Step 4's answer: a redirect for something a person is looking at, a 401
     * for anything else.
     *
     * A document request is a GET that is not a fragment. Everything else —
     * `/r/` because it is a fragment, `/a/` and every other POST because a
     * redirect discards the body anyway — gets the status, so a client can
     * tell "sign in again" apart from "here is a page".
     *
     * The redirect carries no return address. Building one here would mean
     * writing a user-controlled path into a `Location` header at the exact
     * moment nobody is authenticated, which is the open-redirect surface
     * `ReturnAddress` exists to keep closed; the login handler that owns the
     * return address should read the intended URL from the session instead.
     */
    private function unauthenticated(Route $route, Request $request): Response
    {
        if ($request->method === 'GET' && $route->name !== 'region') {
            return Response::redirect($this->urls->route('login.form'));
        }

        throw new UnauthorizedException();
    }
}
