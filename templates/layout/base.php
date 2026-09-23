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
<html class="ra-html" lang="en"<?= $attrs(['data-bs-theme' => $theme]) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($view->title) ?></title>
    <?php foreach ($view->shell->styles as $style) { ?>
        <link class="ra-stylesheet" rel="stylesheet" href="<?= $href($style) ?>">
    <?php } ?>
</head>
<body class="<?= $e($view->bodyClasses()) ?>">
    <nav class="ra-navbar navbar" data-ra-navbar>
        <div class="ra-navbar-inner container-fluid">
            <a class="ra-navbar-brand navbar-brand" href="<?= $href($route('dashboard')) ?>"><?= $e($view->shell->brand) ?></a>

            <ul class="ra-menu navbar-nav">
                <?php foreach ($view->shell->menu as $item) { ?>
                    <li class="ra-menu-item-entry nav-item<?= $item->children !== [] ? ' dropdown' : '' ?>">
                        <a class="<?= $e($item->classes()) ?>" href="<?= $href($item->url) ?>"><?= $e($item->label) ?></a>
                        <?php if ($item->children !== []) { ?>
                            <ul class="ra-submenu dropdown-menu">
                                <?php foreach ($item->children as $child) { ?>
                                    <li class="ra-submenu-entry">
                                        <a class="<?= $e($child->classes()) ?>" href="<?= $href($child->url) ?>"><?= $e($child->label) ?></a>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>

            <?php if ($view->shell->userName !== null) { ?>
                <div class="ra-user-menu dropdown">
                    <span class="ra-user-name"><?= $e($view->shell->userName) ?></span>
                    <?php if ($view->shell->profileUrl !== null) { ?>
                        <a class="ra-profile-link" href="<?= $href($view->shell->profileUrl) ?>">Profile</a>
                    <?php } ?>
                    <?php if ($view->shell->logoutUrl !== null) { ?>
                        <form class="ra-logout-form" method="post" action="<?= $href($view->shell->logoutUrl) ?>">
                            <button class="ra-logout-button btn btn-link" type="submit">Log out</button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </nav>

    <main class="ra-main"><?= $raw($view->slot('main')) ?></main>

    <?= $partial('ui/modal') ?>

    <div class="ra-offcanvas offcanvas offcanvas-end" tabindex="-1" id="ra-offcanvas" aria-hidden="true" data-ra-offcanvas>
        <div class="ra-offcanvas-header offcanvas-header">
            <h5 class="ra-offcanvas-title offcanvas-title"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="ra-offcanvas-body offcanvas-body"></div>
    </div>

    <div class="ra-toast-container toast-container" aria-live="polite" aria-atomic="true">
        <?php foreach ($view->shell->flashes as $flash) { ?>
            <?= $partial('ui/toast', $flash) ?>
        <?php } ?>
    </div>

    <?php foreach ($view->shell->scripts as $script) { ?>
        <script class="ra-script" src="<?= $href($script) ?>"></script>
    <?php } ?>
</body>
</html>
