<?php

/**
 * The page title, its optional description, and one control per configured
 * action.
 *
 * Two lists, because they are two things. `$buttons` is whatever the host
 * handed over as a `ButtonView`, which is always a link. `$actions` is the
 * page's own `header.buttons` block (spec 8.5), built by `ActionBuilder` and
 * already filtered by the gate, and one of those may be a `post` — a form with
 * a token rather than an anchor. `ui/action` draws either, and is the same
 * partial a grid's action cell uses, so a header button and a row button cannot
 * end up looking like two different mechanisms.
 *
 * @var \RockAdmin\View\PageView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(string, mixed=): string $partial
 */
?>
<header class="ra-page-header">
    <div class="ra-page-header-text ra-page-header__left">
        <h1 class="ra-page-title ra-page-header__title"><?= $e($view->title) ?></h1>
        <?php if ($view->description !== '') { ?>
            <p class="ra-page-description ra-page-header__desc"><?= $e($view->description) ?></p>
        <?php } ?>
    </div>
    <?php if ($view->buttons !== [] || $view->actions !== []) { ?>
        <div class="ra-page-actions ra-page-header__right">
            <?php foreach ($view->buttons as $button) { ?>
                <?= $partial('ui/button', $button) ?>
            <?php } ?>
            <?php foreach ($view->actions as $action) { ?>
                <?= $partial('ui/action', $action) ?>
            <?php } ?>
        </div>
    <?php } ?>
</header>
