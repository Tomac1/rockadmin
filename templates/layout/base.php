<?php

/**
 * The document shell: <html>, the navbar, the main region, and the shared
 * containers a later milestone fills from a fragment. Everything below the
 * navbar is whatever the requested inner layout already rendered into the
 * 'main' slot — this template does not know about layouts, regions or
 * pages, only about the page it has been handed.
 *
 * @var \RockAdmin\View\PageView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $raw
 * @var \Closure(mixed): string $href
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(string, array<string, int|string>=, array<string, int|string>=): string $route
 * @var \Closure(string, mixed=): string $partial
 */

$theme = $view->shell->themeAttribute();
?>
<!doctype html>
<html class="ra-html" lang="en"<?= $attrs(['data-ra-theme' => $theme]) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($view->title) ?></title>
    <?php foreach ($view->shell->styles as $style) { ?>
        <link class="ra-stylesheet" rel="stylesheet" href="<?= $href($style) ?>">
    <?php } ?>
</head>
<body class="<?= $e($view->bodyClasses()) ?>">
    <?php
    /*
     * Two bars, which is what the design system is: a dark top bar carrying
     * identity -- brand, workspace, account -- and a darker second bar
     * carrying the pages. Splitting them is not decoration. The top bar
     * answers "where am I signed in and as whom", which does not change as
     * you move around; the second answers "which page", which is the only
     * thing that does. One bar made every page re-read both.
     *
     * Both are `position: sticky` in the design system's own CSS, the second
     * offset by the first's height, so a long grid keeps its navigation.
     */
    ?>
    <nav class="ra-topnav ra-navbar" data-ra-navbar>
        <a class="ra-topnav__brand ra-navbar-brand" href="<?= $href($route('dashboard')) ?>">
            <span class="ra-topnav__brand-mark" aria-hidden="true"></span>
            <span class="ra-topnav__brand-name"><?= $e($view->shell->brand) ?></span>
        </a>

        <?php
        /*
         * The workspace switcher. It renders only when a list is supplied, and
         * `ShellWorkspaces` supplies one only when there are at least two
         * workspaces to move between: a switcher with a single button is a
         * control that cannot do anything, which is the
         * visible-button-that-does-nothing this project refuses.
         *
         * Three kinds of entry come through here and the markup does not care
         * which is which -- automatic, each workspace, and all of them. That
         * is deliberate: which one is current is `$workspace->active`, decided
         * where the states are known, and a template working it out from the
         * labels would be a second place that has to agree about them.
         */
        ?>
        <?php if ($view->shell->workspaces !== []) { ?>
            <?php
            /*
             * A form, not links. Specification 5.1: switching changes what
             * somebody sees for the rest of the session, and browsers
             * prefetch links on hover — a GET here would let a hover over the
             * switcher quietly change the active workspace.
             *
             * One form and a `formaction` per button, the way the bulk bar
             * does it: the token is written once and which workspace is
             * chosen is the button that was pressed.
             *
             * `_ret` is where to come back to, so switching reloads the page
             * you were looking at in the new workspace. The handler validates
             * it as an internal path and falls back to the dashboard, because
             * the page you were on may not be in the workspace you just moved
             * to.
             */
            ?>
            <form class="ra-topnav__projects ra-workspaces" method="post" action="<?= $href($view->shell->workspaces[0]->url) ?>">
                <?php if ($view->shell->csrfToken !== null) { ?>
                    <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->shell->csrfToken) ?>">
                <?php } ?>
                <input type="hidden" name="<?= $e(\RockAdmin\Form\FormFields::RETURN_TO) ?>" value="<?= $e($view->shell->currentUrl) ?>">
                <?php foreach ($view->shell->workspaces as $workspace) { ?>
                    <button
                        class="ra-topnav__proj-btn ra-workspace<?= $e($workspace->active ? ' ra-topnav__proj-btn--active' : '') ?>"
                        type="submit"
                        formaction="<?= $href($workspace->url) ?>"
                        <?= $e($workspace->active ? 'aria-current="true"' : '') ?>
                    >
                        <span class="ra-topnav__proj-dot" aria-hidden="true"></span><?= $e($workspace->label) ?>
                    </button>
                <?php } ?>
            </form>
        <?php } ?>

        <span class="ra-topnav__spacer"></span>

        <?php if ($view->shell->userName !== null) { ?>
            <div class="ra-topnav__right ra-user-menu">
                <span class="ra-topnav__trigger ra-user-name"><?= $e($view->shell->userName) ?></span>
                <?php if ($view->shell->profileUrl !== null) { ?>
                    <a class="ra-topnav__iconbtn ra-profile-link" href="<?= $href($view->shell->profileUrl) ?>">Profile</a>
                <?php } ?>
                <?php if ($view->shell->logoutUrl !== null) { ?>
                    <form class="ra-logout-form" method="post" action="<?= $href($view->shell->logoutUrl) ?>">
                        <?php // LogoutHandler refuses a post without a token: a page anywhere
                              // on the internet could otherwise sign an administrator out
                              // mid-edit, as often as it liked, and they would read that as a
                              // broken installation rather than as an attack. ?>
                        <?php if ($view->shell->csrfToken !== null) { ?>
                            <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->shell->csrfToken) ?>">
                        <?php } ?>
                        <button class="ra-btn ra-btn--ghost ra-btn--sm ra-logout-button" type="submit">Log out</button>
                    </form>
                <?php } ?>
            </div>
        <?php } ?>
    </nav>

    <nav class="ra-secnav ra-menu-bar">
        <div class="ra-secnav__inner ra-menu">
            <?php foreach ($view->shell->menu as $item) { ?>
                <div class="ra-snav-item ra-menu-item-entry">
                    <a class="ra-snav-btn <?= $e($item->classes()) ?><?= $e($item->active ? ' ra-snav-btn--active' : '') ?>"
                       href="<?= $href($item->url) ?>"><?= $e($item->label) ?><?php
                        if ($item->count !== null) { ?><span class="ra-snav-badge"><?= $e((string) $item->count) ?></span><?php }
                    ?></a>
                    <?php if ($item->children !== []) { ?>
                        <div class="ra-snav-dd ra-submenu">
                            <?php foreach ($item->children as $child) { ?>
                                <a class="ra-snav-dd__item<?= $e($child->active ? ' ra-snav-dd__item--active' : '') ?> ra-submenu-entry <?= $e($child->classes()) ?>" href="<?= $href($child->url) ?>">
                                    <span class="ra-snav-dd__item-label"><?= $e($child->label) ?></span>
                                </a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </nav>

    <main class="ra-main"><?= $raw($view->slot('main')) ?></main>

    <?= $partial('ui/modal') ?>

    <?= $partial('ui/confirm') ?>

    <?php
    /*
     * The shared offcanvas, in the same two parts as the modal: the dark
     * sheet carries the id and the panel sits inside it. `data-ra-close`
     * rather than `data-ra-dismiss` -- dismiss removes its target, and a
     * container removed is one the next action cannot fill.
     */
    ?>
    <div class="ra-offcanvas-overlay ra-offcanvas-backdrop" id="ra-offcanvas" hidden data-ra-offcanvas data-ra-close="offcanvas">
        <div class="ra-offcanvas ra-offcanvas--right" role="dialog" aria-modal="true" aria-labelledby="ra-offcanvas-title" tabindex="-1">
            <div class="ra-offcanvas__header ra-offcanvas-header">
                <h2 class="ra-offcanvas__title ra-offcanvas-title" id="ra-offcanvas-title"></h2>
                <button class="ra-offcanvas__close ra-offcanvas-close" type="button" data-ra-close="offcanvas" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                </button>
            </div>
            <div class="ra-offcanvas__body ra-offcanvas-body"></div>
        </div>
    </div>

    <div class="ra-toast-container" aria-live="polite" aria-atomic="true">
        <?php foreach ($view->shell->flashes as $flash) { ?>
            <?= $partial('ui/toast', $flash) ?>
        <?php } ?>
    </div>

    <?php foreach ($view->shell->scripts as $script) { ?>
        <script class="ra-script" src="<?= $href($script) ?>"></script>
    <?php } ?>
</body>
</html>
