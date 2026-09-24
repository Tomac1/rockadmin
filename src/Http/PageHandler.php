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
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
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
    /** @param list<MenuItemView> $menu */
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ListRegion $region,
        private readonly Renderer $renderer,
        private readonly Assets $assets,
        private readonly FlashBag $flashes,
        private readonly string $brand,
        private readonly string $darkMode,
        private readonly array $menu = [],
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
            darkMode: $this->darkMode,
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
        return array_map(
            static fn (MenuItemView $item): MenuItemView => new MenuItemView(
                $item->label,
                $item->url,
                $item->icon,
                active: $item->key === $currentPage,
                children: $item->children,
                key: $item->key,
            ),
            $this->menu,
        );
    }
}
