<?php

/**
 * The two steps of a password reset: asking for a link, and setting the
 * password behind one.
 *
 * `page/login`'s sibling and deliberately its twin — a whole document with no
 * shell navigation, no script, and no toast. The reasoning is written out
 * there and holds here for the same reasons: a menu is a list of pages
 * somebody may open and the person reading this is nobody yet, the form is an
 * ordinary POST that works with JavaScript off, and a Bootstrap toast would
 * never become visible on a page that loads no script.
 *
 * One template for both steps, told apart by the view's mode. They are the
 * same page with a different field in the middle, and two copies would be two
 * places to keep that agreement.
 *
 * @var \RockAdmin\View\PasswordResetView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$theme = $view->shell->themeAttribute();
$emailId = 'ra-password-email';
$passwordId = 'ra-password-new';
$confirmId = 'ra-password-confirm';
?>
<!doctype html>
<html class="ra-html" lang="en"<?= $attrs(['data-ra-theme' => $theme]) ?>>
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
            <p class="ra-login-title ra-password-title"><?= $e($view->title) ?></p>

            <?php if ($view->notice !== null) { ?>
                <div class="ra-alert ra-alert--info ra-password-notice" role="status">
                    <p class="ra-alert__title ra-password-notice-text"><?= $e($view->notice) ?></p>
                </div>
            <?php } ?>

            <?php if ($view->message !== null) { ?>
                <div class="ra-alert ra-alert--error ra-password-message" role="alert">
                    <p class="ra-alert__title ra-password-message-text"><?= $e($view->message) ?></p>
                    <?php if ($view->hint !== '') { ?>
                        <p class="ra-alert__desc ra-password-message-hint"><?= $e($view->hint) ?></p>
                    <?php } ?>
                </div>
            <?php } ?>

            <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->token) ?>">

            <?php if ($view->isRequest()) { ?>
                <p class="ra-password-intro">
                    Type the address you sign in with. If it belongs to an administrator, a link to
                    set a new password will be sent to it.
                </p>

                <div class="ra-login-field ra-password-field-email">
                    <label class="ra-field__label ra-login-label" for="<?= $e($emailId) ?>">Email</label>
                    <input class="ra-input ra-login-control" type="email" id="<?= $e($emailId) ?>" name="<?= $e(\RockAdmin\Http\PasswordResetHandler::EMAIL_FIELD) ?>" value="<?= $e($view->email) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required>
                </div>
            <?php } else { ?>
                <?php // No value attribute on either control, and no property
                      // on the view that could fill one. See PasswordResetView. ?>
                <div class="ra-login-field ra-password-field-new">
                    <label class="ra-field__label ra-login-label" for="<?= $e($passwordId) ?>">New password</label>
                    <input class="ra-input ra-login-control" type="password" id="<?= $e($passwordId) ?>" name="<?= $e(\RockAdmin\Http\PasswordResetHandler::PASSWORD_FIELD) ?>" value="" autocomplete="new-password" minlength="<?= $e(\RockAdmin\Http\PasswordResetHandler::MINIMUM_LENGTH) ?>" autofocus required>
                </div>

                <div class="ra-login-field ra-password-field-confirm">
                    <label class="ra-field__label ra-login-label" for="<?= $e($confirmId) ?>">Again</label>
                    <input class="ra-input ra-login-control" type="password" id="<?= $e($confirmId) ?>" name="<?= $e(\RockAdmin\Http\PasswordResetHandler::CONFIRM_FIELD) ?>" value="" autocomplete="new-password" required>
                </div>
            <?php } ?>

            <div class="ra-login-actions">
                <button class="ra-btn ra-btn--primary ra-btn--lg ra-btn--full ra-password-submit" type="submit">
                    <?= $e($view->isRequest() ? 'Send the link' : 'Set the password') ?>
                </button>
            </div>

            <p class="ra-password-back">
                <a class="ra-link ra-password-back-link" href="<?= $href($view->loginUrl) ?>">Back to sign in</a>
            </p>
        </form>
    </main>
</body>
</html>
