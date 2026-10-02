<?php

/**
 * "An account has been made for you."
 *
 * The same shape as the reset, and deliberately the same mechanism underneath
 * -- a single-use token with a longer fuse. What differs is what the person
 * knows: nobody asked for this message, so it says who the admin is for and
 * what the account is, and it does not tell them to ignore it if they were not
 * expecting it. An invitation nobody expected is a question for the person who
 * sent it, not something to throw away.
 *
 * @var \RockAdmin\View\MailView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<h1 style="margin:0 0 16px 0; font-size:20px; line-height:1.3;"><?= $e($view->title) ?></h1>

<p style="margin:0 0 16px 0;">Hello <?= $e($view->recipient) ?>,</p>

<p style="margin:0 0 16px 0;">
    An administrator account has been created for you in <?= $e($view->brand) ?>.
    Choose a password to start using it.
</p>

<p style="margin:0 0 24px 0;">
    <a href="<?= $href($view->url) ?>" style="display:inline-block; padding:12px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-weight:bold;">Choose a password</a>
</p>

<p style="margin:0 0 16px 0; font-size:14px; color:#6b7280;">
    The link works once and expires in <?= $e($view->expiry) ?>. If the button does not
    work, copy this address into your browser:<br>
    <span style="word-break:break-all;"><?= $e($view->url) ?></span>
</p>

<p style="margin:0; font-size:14px; color:#6b7280;">
    If you were not expecting this, ask whoever runs <?= $e($view->brand) ?> about it
    before using the link.
</p>
