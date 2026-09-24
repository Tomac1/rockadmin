<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Grid\GridState;
use RockAdmin\Grid\ListRegion;
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
 * Reads its `GridState` from the request's query exactly the way
 * `PageHandler` does for the same region, which is what keeps
 * `/p/{page}?grid[page]=2` and `/r/{page}/grid?grid[page]=2` in agreement.
 */
final class RegionHandler implements Handler
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly ListRegion $region,
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

        if ($regionDefinition->type !== RegionType::List) {
            throw new NotFoundException(
                "Page '{$name}': region '{$regionKey}' is a '{$regionDefinition->type->value}' region, "
                . 'which this milestone does not render.',
            );
        }

        $state = GridState::fromQuery($request->query, $regionKey, $regionDefinition);
        $view = $this->region->render($page, $regionDefinition, $state);

        return Response::html($this->renderer->render('region/list/region', $view));
    }
}
