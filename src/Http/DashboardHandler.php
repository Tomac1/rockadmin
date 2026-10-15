<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\View\Assets;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Menu;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellAccount;
use RockAdmin\View\ShellView;

/**
 * The admin's front page.
 *
 * It exists because the navbar's brand links here and because `/admin` with
 * nothing after it is where everybody arrives: an installation whose home
 * page is a 404 is one that looks broken before it has been used once. Until
 * this class existed the only `dashboard` handler in the project was an
 * anonymous class inside the demo, which meant every host had to write its
 * own before the admin would answer its own front door.
 *
 * **It draws what the reader may open and nothing else.** No statistics, no
 * recent activity, no chart — those need a `stat` region and configuration to
 * say what to count, and both belong to the built-in-pages milestone. What it
 * can honestly show today is the menu, filtered by the gate, as something
 * larger than a navigation bar: the pages this person administers.
 *
 * A host that wants its own front page registers a handler against
 * `dashboard` through `Setup`, and this one is never built.
 */
final class DashboardHandler implements Handler
{
    public function __construct(
        private readonly Renderer $renderer,
        private readonly Assets $assets,
        private readonly FlashBag $flashes,
        private readonly Menu $menu,
        private readonly ShellAccount $account,
        private readonly string $brand,
        private readonly string $darkMode,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $shell = new ShellView(
            $this->brand,
            menu: $this->menu->forPage('dashboard'),
            flashes: $this->flashes->take(),
            styles: $this->assets->styles(),
            scripts: $this->assets->scripts(),
            userName: $this->account->userName(),
            logoutUrl: $this->account->logoutUrl(),
            darkMode: $this->darkMode,
            // Never the URL without the token: the Log out button would
            // answer 403, which is the kind of bug that looks like a session
            // problem for an afternoon.
            csrfToken: $this->account->csrfToken(),
        );

        $view = new PageView(
            'dashboard',
            $this->brand,
            $shell,
            description: 'What you administer here.',
        );

        $main = $this->renderer->render('page/dashboard', $view);

        $single = $this->renderer->render(
            'layout/single',
            new PageView('dashboard', $this->brand, $shell, slots: ['main' => $main]),
        );

        return Response::html($this->renderer->render(
            'layout/base',
            new PageView('dashboard', $this->brand, $shell, slots: ['main' => $single]),
        ));
    }
}
