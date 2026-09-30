<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Form\FormView;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\View\Assets;
use RockAdmin\View\FlashBag;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;

/**
 * Draws a form: `GET p/{page}/create`, `p/{page}/{id}/edit` and
 * `p/{page}/{id}/copy`.
 *
 * All three are the same request with a different starting point, which is
 * why they are one handler rather than three: the document, the shell, the
 * region and the 404 rules are identical, and the only thing the route name
 * decides is which `FormRegion` method fills the fields in.
 *
 * `document()` is public because `ActionHandler` redraws a refused submission
 * through it. That is the seam that keeps rule 7's promise visible in the
 * code: a rejected form is not "a form that looks like the original", it is
 * the original, rendered by the same method from a `FormView` the same class
 * built.
 *
 * `_ret` arrives here in the query string, because a form is reached by
 * following a link from a grid. It is validated by `ReturnAddress` and never
 * by this class or by `FormRegion`, both of which only carry it: deciding
 * what an acceptable address is belongs in one place, and that place is the
 * one with the adversarial test suite.
 */
final class FormHandler implements Handler
{
    /** @param list<MenuItemView> $menu */
    public function __construct(
        private readonly PageRepository $pages,
        private readonly FormRegion $region,
        private readonly Renderer $renderer,
        private readonly Assets $assets,
        private readonly FlashBag $flashes,
        private readonly UrlGenerator $urls,
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
        $region = self::formRegionOf($page);
        $returnTo = self::returnTo($request->query(FormFields::RETURN_TO), $this->urls);

        $view = match ($route->name) {
            'page.create' => $this->region->create($page, $region, $returnTo),
            'page.edit' => $this->region->edit($page, $region, $route->param('id'), $returnTo),
            'page.copy' => $this->region->copy($page, $region, $route->param('id'), $returnTo),
            default => throw new NotFoundException(
                "Route '{$route->name}' does not draw a form.",
            ),
        };

        if ($view === null) {
            throw new NotFoundException("Page '{$name}' has no row '{$route->param('id')}'.");
        }

        return $this->document($page, $view);
    }

    /**
     * The page's form region, or a 404 naming what is missing.
     *
     * Static and shared with `ActionHandler` so that a page which draws no
     * form cannot be written to either: the two would otherwise be free to
     * disagree about whether a page has a form at all, and the one that said
     * yes would be the one that writes.
     */
    public static function formRegionOf(PageDefinition $page): RegionDefinition
    {
        $region = $page->firstFormRegion();

        if ($region === null) {
            throw new NotFoundException("Page '{$page->name}' has no form region.");
        }

        return $region;
    }

    /**
     * A `_ret` value turned into the URL to come back to, or null so that
     * `FormRegion` falls back to the page's own index.
     *
     * Null, never `''`: `FormRegion` treats an empty string as an address and
     * would carry it into the document.
     */
    public static function returnTo(mixed $raw, UrlGenerator $urls): ?string
    {
        return ReturnAddress::from($raw)?->url($urls);
    }

    /**
     * One form, as a whole document. `$status` is 200 when the form is being
     * drawn and 422 when it is being redrawn over a refused submission —
     * the same document either way, because it is the same form.
     */
    public function document(PageDefinition $page, FormView $view, int $status = 200): Response
    {
        $shell = new ShellView(
            $this->brand,
            menu: $this->menuFor($page->name),
            flashes: $this->flashes->take(),
            styles: $this->assets->styles(),
            scripts: $this->assets->scripts(),
            darkMode: $this->darkMode,
        );

        $header = new PageView($page->name, $view->title, $shell, description: $page->description);

        $main = $this->renderer->render('page/header', $header)
            . $this->renderer->render('region/form/region', $view);

        $layout = $this->renderer->render(
            'layout/' . $page->layout,
            new PageView($page->name, $view->title, $shell, slots: ['main' => $main]),
        );

        $html = $this->renderer->render(
            'layout/base',
            new PageView($page->name, $view->title, $shell, slots: ['main' => $layout]),
        );

        return Response::html($html, $status);
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
