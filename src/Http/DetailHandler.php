<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;
use RockAdmin\View\Assets;
use RockAdmin\View\FlashBag;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;

/**
 * Renders the whole document for `GET /p/{page}/{id}`: the shell, the page
 * header, and the page's preview region rendered for the one row the URL
 * names.
 *
 * A page with more than one preview region renders the first one it finds —
 * this milestone's row detail page shows one row one way; a page wanting
 * several previews of the same row is a later milestone's problem. A page
 * with no preview region at all, or a row the id names nothing, are both
 * 404s: the first because `/p/{page}/{id}` promises a row and the page
 * cannot show one, the second because the id itself is empty.
 *
 * `PageHandler` and `DetailHandler` share the shell-building shape on
 * purpose — the same brand, menu, flashes, assets and dark mode setting
 * build every document this admin serves, whichever region ends up inside
 * it.
 */
final class DetailHandler implements Handler
{
    /** @param list<MenuItemView> $menu */
    public function __construct(
        private readonly PageRepository $pages,
        private readonly PreviewRegion $region,
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
        $id = $route->param('id');

        $regionDefinition = $this->firstPreviewRegion($page->regions);

        if ($regionDefinition === null) {
            throw new NotFoundException("Page '{$name}' has no preview region to show a row with.");
        }

        $view = $this->region->render($page, $regionDefinition, $id);

        if ($view === null) {
            throw new NotFoundException("Page '{$name}' has no row '{$id}'.");
        }

        $shell = new ShellView(
            $this->brand,
            menu: $this->menuFor($name),
            flashes: $this->flashes->take(),
            styles: $this->assets->styles(),
            scripts: $this->assets->scripts(),
            darkMode: $this->darkMode,
        );

        $header = new PageView($page->name, $view->title, $shell, description: $page->description);

        $main = $this->renderer->render('page/header', $header)
            . $this->renderer->render('region/preview/region', $view);

        $layout = $this->renderer->render(
            'layout/' . $page->layout,
            new PageView($page->name, $view->title, $shell, slots: ['main' => $main]),
        );

        $html = $this->renderer->render(
            'layout/base',
            new PageView($page->name, $view->title, $shell, slots: ['main' => $layout]),
        );

        return Response::html($html);
    }

    /** @param array<string, RegionDefinition> $regions */
    private function firstPreviewRegion(array $regions): ?RegionDefinition
    {
        foreach ($regions as $region) {
            if ($region->type === RegionType::Preview) {
                return $region;
            }
        }

        return null;
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
