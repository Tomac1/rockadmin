<?php

/**
 * The page title, its optional description, and one button per configured
 * action.
 *
 * @var \RockAdmin\View\PageView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(string, mixed=): string $partial
 */
?>
<header class="ra-page-header">
    <div class="ra-page-header-text">
        <h1 class="ra-page-title"><?= $e($view->title) ?></h1>
        <?php if ($view->description !== '') { ?>
            <p class="ra-page-description"><?= $e($view->description) ?></p>
        <?php } ?>
    </div>
    <?php if ($view->buttons !== []) { ?>
        <div class="ra-page-actions">
            <?php foreach ($view->buttons as $button) { ?>
                <?= $partial('ui/button', $button) ?>
            <?php } ?>
        </div>
    <?php } ?>
</header>
