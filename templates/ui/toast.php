<?php

/**
 * One flash message, shown once as a dismissible toast.
 *
 * **No `show` class, and that is the point.** Bootstrap hid a toast without
 * one — `.toast:not(.show) { display: none }` — which is how this admin
 * shipped a flash message that was in the markup, carried the right classes
 * and the right text, and was invisible for a whole milestone while a test
 * asserted all three. The design system's `.ra-toast` is visible because it
 * exists, so there is no second state to get wrong.
 *
 * The close button is `data-ra-dismiss`, bound by delegation in core.js,
 * because rule 6 forbids a script tag in a template and a toast can arrive
 * by AJAX.
 *
 * @var \RockAdmin\View\FlashView $view
 * @var \Closure(mixed): string $e
 */
?>
<div class="<?= $e($view->classes()) ?>" role="status" aria-live="polite" aria-atomic="true" data-ra-toast>
    <div class="ra-toast__body ra-toast-body">
        <span class="ra-toast__message ra-toast-message"><?= $e($view->message) ?></span>
        <button type="button" class="ra-toast__close ra-toast-close" data-ra-dismiss="toast" aria-label="Close"></button>
    </div>
</div>
