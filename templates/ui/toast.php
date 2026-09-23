<?php

/**
 * One flash message, shown once as a dismissible toast. core.js has no
 * behaviour registered for it yet — the close button works because it is
 * a Bootstrap toast, wired by its own data-bs-* attributes.
 *
 * @var \RockAdmin\View\FlashView $view
 * @var \Closure(mixed): string $e
 */
?>
<div class="<?= $e($view->classes()) ?> toast" role="status" aria-live="polite" aria-atomic="true" data-ra-toast>
    <div class="ra-toast-body toast-body">
        <span class="ra-toast-message"><?= $e($view->message) ?></span>
        <button type="button" class="ra-toast-close btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>
