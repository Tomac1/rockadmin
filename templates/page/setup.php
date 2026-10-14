<?php

/**
 * Creating the first administrator, on an installation that has none.
 *
 * `page/login`'s sibling: a whole document, no shell navigation, no script, no
 * toast — the reasoning is written out there and holds here for the same
 * reasons, and the person reading this page is more obviously nobody yet than
 * on any other page in the admin.
 *
 * The page says what the account will be given. An installation's first
 * account is the one that can create every other, and somebody who thinks they
 * are making an ordinary login and is in fact made a superadmin has not been
 * asked.
 *
 * @var \RockAdmin\View\SetupView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$theme = $view->shell->themeAttribute();
$emailId = 'ra-setup-email';
$nameId = 'ra-setup-name';
$passwordId = 'ra-setup-password';
$confirmId = 'ra-setup-confirm';
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
            <p class="ra-login-title ra-setup-title"><?= $e($view->title) ?></p>

            <p class="ra-setup-intro">
                This admin has no administrators yet. The account made here is given the
                <strong class="ra-setup-role"><?= $e($view->role) ?></strong> role<?php
                    if ($view->workspaces > 0) { ?> and access to all
                    <?= $e((string) $view->workspaces) ?> workspaces<?php } ?>,
                and can create the rest. This page stops working the moment it exists.
            </p>

            <?php if ($view->message !== null) { ?>
                <div class="ra-alert ra-alert--error ra-setup-message" role="alert">
                    <p class="ra-alert__title ra-setup-message-text"><?= $e($view->message) ?></p>
                </div>
            <?php } ?>

            <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->token) ?>">

            <div class="ra-login-field ra-setup-field-name">
                <label class="ra-field__label ra-login-label" for="<?= $e($nameId) ?>">Name</label>
                <input class="ra-input ra-login-control" type="text" id="<?= $e($nameId) ?>" name="<?= $e(\RockAdmin\Http\SetupHandler::NAME_FIELD) ?>" value="<?= $e($view->name) ?>" autocomplete="name" autofocus required>
            </div>

            <div class="ra-login-field ra-setup-field-email">
                <label class="ra-field__label ra-login-label" for="<?= $e($emailId) ?>">Email</label>
                <input class="ra-input ra-login-control" type="email" id="<?= $e($emailId) ?>" name="<?= $e(\RockAdmin\Http\SetupHandler::EMAIL_FIELD) ?>" value="<?= $e($view->email) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required>
            </div>

            <?php // No value attribute on either control, and no property on
                  // the view that could fill one. See SetupView. ?>
            <div class="ra-login-field ra-setup-field-password">
                <label class="ra-field__label ra-login-label" for="<?= $e($passwordId) ?>">Password</label>
                <input class="ra-input ra-login-control" type="password" id="<?= $e($passwordId) ?>" name="<?= $e(\RockAdmin\Http\SetupHandler::PASSWORD_FIELD) ?>" value="" autocomplete="new-password" minlength="<?= $e(\RockAdmin\Http\PasswordResetHandler::MINIMUM_LENGTH) ?>" required>
            </div>

            <div class="ra-login-field ra-setup-field-confirm">
                <label class="ra-field__label ra-login-label" for="<?= $e($confirmId) ?>">Again</label>
                <input class="ra-input ra-login-control" type="password" id="<?= $e($confirmId) ?>" name="<?= $e(\RockAdmin\Http\SetupHandler::CONFIRM_FIELD) ?>" value="" autocomplete="new-password" required>
            </div>

            <div class="ra-login-actions">
                <button class="ra-btn ra-btn--primary ra-btn--lg ra-btn--full ra-setup-submit" type="submit">
                    Create it and sign in
                </button>
            </div>
        </form>
    </main>
</body>
</html>
