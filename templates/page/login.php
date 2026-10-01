<?php

/**
 * The login page: a whole document, and the only page in this admin drawn
 * without the shell.
 *
 * It writes its own `<html>` rather than going through `layout/base` because
 * everything that layout exists for — the navbar, the menu, the user menu,
 * the offcanvas, the modal — is a list of things somebody may do, and the
 * person reading this page is nobody yet. A navbar with an empty menu and a
 * brand linking to a dashboard that would bounce them straight back here is
 * furniture, not navigation.
 *
 * **It loads no script, deliberately.** The form is an ordinary POST and
 * works with JavaScript switched off, so there is nothing for `core.js` to
 * bind to; `layout/base` is the one template allowed to write a `<script>`
 * tag, and this page needs none. The consequence is worth stating because it
 * decides something else: a flash message drawn here as a Bootstrap toast
 * would never become visible, since `.toast` is `display: none` until a
 * script shows it. So this page draws no toasts, and the one message it has
 * to show is an `alert` in the flow of the form.
 *
 * The field ids are named once, at the top, and read twice each — by the
 * label's `for` and by the control's `id`. Milestone 6 shipped a filter whose
 * label pointed at nothing, which is what a second hand-written spelling
 * looks like after one of them changes.
 *
 * @var \RockAdmin\View\LoginView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$theme = $view->shell->themeAttribute();
$emailId = 'ra-login-email';
$passwordId = 'ra-login-password';
?>
<!doctype html>
<html class="ra-html" lang="en"<?= $attrs(['data-bs-theme' => $theme]) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($view->title) ?> &middot; <?= $e($view->shell->brand) ?></title>
    <?php foreach ($view->shell->styles as $style) { ?>
        <link class="ra-stylesheet" rel="stylesheet" href="<?= $href($style) ?>">
    <?php } ?>
</head>
<body class="<?= $e($view->bodyClasses()) ?>">
    <main class="ra-login-main">
        <form class="<?= $e($view->classes()) ?>" method="post" action="<?= $href($view->action) ?>">
            <h1 class="ra-login-brand"><?= $e($view->shell->brand) ?></h1>
            <p class="ra-login-title"><?= $e($view->title) ?></p>

            <?php if ($view->message !== null) { ?>
                <div class="ra-alert ra-alert--error ra-login-message" role="alert">
                    <p class="ra-alert__title ra-login-message-text"><?= $e($view->message) ?></p>
                    <?php if ($view->hint !== '') { ?>
                        <p class="ra-alert__desc ra-login-message-hint"><?= $e($view->hint) ?></p>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php // The three body keys are written through their constants,
                  // never spelled out: the handler reads all three back
                  // through the same ones, and a contract spelled from memory
                  // on each side is one rename away from a form that posts
                  // and signs nobody in. ?>
            <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->token) ?>">
            <input type="hidden" name="<?= $e(\RockAdmin\Form\FormFields::RETURN_TO) ?>" value="<?= $e($view->returnTo) ?>">

            <div class="ra-login-field ra-login-field-email">
                <label class="ra-field__label ra-login-label" for="<?= $e($emailId) ?>">Email</label>
                <input class="ra-input ra-login-control" type="email" id="<?= $e($emailId) ?>" name="<?= $e(\RockAdmin\Http\LoginHandler::EMAIL_FIELD) ?>" value="<?= $e($view->email) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required>
            </div>

            <div class="ra-login-field ra-login-field-password">
                <label class="ra-login-label form-label" for="<?= $e($passwordId) ?>">Password</label>
                <?php // No value attribute is written at all, and the view has
                      // no property that could fill one. See LoginView. ?>
                <input class="ra-input ra-login-control" type="password" id="<?= $e($passwordId) ?>" name="<?= $e(\RockAdmin\Http\LoginHandler::PASSWORD_FIELD) ?>" value="" autocomplete="current-password" required>
            </div>

            <div class="ra-login-actions">
                <button class="ra-btn ra-btn--primary ra-btn--lg ra-btn--full ra-login-submit" type="submit">Sign in</button>
            </div>
        </form>
    </main>
</body>
</html>
