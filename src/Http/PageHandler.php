<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Grid\GridState;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionType;
use RockAdmin\View\Assets;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Menu;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellAccount;
use RockAdmin\View\ShellView;

/**
 * Renders the whole document for `GET /p/{page}`: the shell, the page header
 * from its `header` block, and each region rendered into the layout's main
 * slot.
 *
 * A page name that names no file, or names one whose region machinery is not
 * this milestone's (only `list` renders — `preview` regions are reached
 * through `/r/{page}/{region}` on their own, never rendered inline), is
 * simply left out of the page rather than failing the whole request: a
 * `preview` region exists to be loaded into a modal, not to appear on the
 * page it belongs to.
 *
 * `PageHandler` and `RegionHandler` share the same `ListRegion` and the same
 * `GridState::fromQuery()` call, reading the same request query — the seam
 * that keeps a full page and its own AJAX fragment from ever disagreeing on
 * what a URL means.
 */
final class PageHandler implements Handler
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ListRegion $region,
        private readonly Renderer $renderer,
        private readonly Assets $assets,
        private readonly FlashBag $flashes,
        private readonly string $brand,
        private readonly string $darkMode,
        /**
         * The navigation, or null for an admin that draws none. `Menu` decides
         * both what this reader may see (spec 9.2: never a link that answers
         * 403) and which item is active. It used to be a plain list, and each
         * of the three document handlers carried its own copy of the active
         * rule; a handler that filtered or highlighted items itself would be
         * that duplication again, now over a permission check.
         */
        private readonly ?Menu $menu = null,
        /**
         * The navbar's account area: who is signed in, and the Log out button
         * with the token it has to carry. Null draws no account area, which is
         * what a shell without one looks like.
         */
        private readonly ?ShellAccount $account = null,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $name = $route->param('page');

        if (!$this->pages->has($name)) {
            throw new NotFoundException("Page '{$name}' not found.");
        }

        $page = $this->pages->get($name);

        $shell = new ShellView(
            $this->brand,
            menu: $this->menuFor($name),
            flashes: $this->flashes->take(),
            styles: $this->assets->styles(),
            scripts: $this->assets->scripts(),
            userName: $this->account?->userName(),
            logoutUrl: $this->account?->logoutUrl(),
            darkMode: $this->darkMode,
            csrfToken: $this->account?->csrfToken(),
        );

        $header = new PageView($page->name, $page->title, $shell, description: $page->description);

        $main = $this->renderer->render('page/header', $header) . $this->regions($page, $request);

        $layout = $this->renderer->render(
            'layout/' . $page->layout,
            new PageView($page->name, $page->title, $shell, slots: ['main' => $main]),
        );

        $html = $this->renderer->render(
            'layout/base',
            new PageView($page->name, $page->title, $shell, slots: ['main' => $layout]),
        );

        return Response::html($html);
    }

    private function regions(PageDefinition $page, Request $request): string
    {
        $html = '';

        foreach ($page->regions as $regionKey => $regionDefinition) {
            if ($regionDefinition->type !== RegionType::List) {
                continue;
            }

            $state = GridState::fromQuery($request->query, $regionKey, $regionDefinition);
            $view = $this->region->render($page, $regionDefinition, $state);

            $html .= $this->renderer->render('region/list/region', $view);
        }

        return $html;
    }

    /** @return list<MenuItemView> */
    private function menuFor(string $currentPage): array
    {
        return $this->menu?->forPage($currentPage) ?? [];
    }
}
