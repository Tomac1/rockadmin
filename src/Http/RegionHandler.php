<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Grid\GridState;
use RockAdmin\Grid\ListRegion;
use RockAdmin\Grid\PreviewRegion;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionType;
use RockAdmin\View\Renderer;

/**
 * Renders one region for `GET /r/{page}/{region}`, and nothing else: no
 * shell, no document, because the answer goes straight into a live page
 * either as the response to a fetch or, with JavaScript off, as the whole
 * page a plain link or GET form navigated to.
 *
 * The fragment is valid HTML on its own — `templates/region/list/region.php`
 * carries `data-ra-region` and `data-ra-region-url` on its own root element —
 * so it can be opened directly in a browser for debugging.
 *
 * A list region reads its `GridState` from the request's query exactly the
 * way `PageHandler` does for the same region, which is what keeps
 * `/p/{page}?grid[page]=2` and `/r/{page}/grid?grid[page]=2` in agreement. A
 * preview region reads the row it shows from `?id=`, since a preview has no
 * grid state of its own — this is the address milestone 8's overlay will
 * fetch without this handler changing at all.
 */
final class RegionHandler implements Handler
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ListRegion $list,
        private readonly PreviewRegion $preview,
        private readonly Renderer $renderer,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $name = $route->param('page');

        if (!$this->pages->has($name)) {
            throw new NotFoundException("Page '{$name}' not found.");
        }

        $page = $this->pages->get($name);
        $regionKey = $route->param('region');

        if (!$page->hasRegion($regionKey)) {
            throw new NotFoundException("Page '{$name}' has no region '{$regionKey}'.");
        }

        $regionDefinition = $page->region($regionKey);

        if ($regionDefinition->type === RegionType::Preview) {
            $id = $request->query('id');
            $id = \is_scalar($id) ? (string) $id : '';

            if ($id === '') {
                throw new NotFoundException(
                    "Page '{$name}': region '{$regionKey}' needs an 'id' query parameter to know which row to show.",
                );
            }

            $view = $this->preview->render($page, $regionDefinition, $id);

            if ($view === null) {
                throw new NotFoundException("Page '{$name}' has no row '{$id}'.");
            }

            return Response::html($this->renderer->render('region/preview/region', $view));
        }

        $state = GridState::fromQuery($request->query, $regionKey, $regionDefinition);
        $view = $this->list->render($page, $regionDefinition, $state);

        return Response::html($this->renderer->render('region/list/region', $view));
    }
}
