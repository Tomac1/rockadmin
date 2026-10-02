<?php

/**
 * "You asked to set a new password."
 *
 * The button and the plain link are both here on purpose: a client that
 * renders the table shows the button, and anybody whose client mangles it can
 * still see the URL and copy it. A message with only a button is a message
 * some people cannot act on.
 *
 * The last paragraph is the one that matters for somebody who did *not* ask:
 * it says that ignoring this leaves the password alone, which is both true and
 * the thing a person worries about when an unexpected reset arrives.
 *
 * @var \RockAdmin\View\MailView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<h1 style="margin:0 0 16px 0; font-size:20px; line-height:1.3;"><?= $e($view->title) ?></h1>

<p style="margin:0 0 16px 0;">Hello <?= $e($view->recipient) ?>,</p>

<p style="margin:0 0 16px 0;">
    Somebody asked to set a new password for your administrator account. If it was
    you, use the link below.
</p>

<p style="margin:0 0 24px 0;">
    <a href="<?= $href($view->url) ?>" style="display:inline-block; padding:12px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:4px; font-weight:bold;">Set a new password</a>
</p>

<p style="margin:0 0 16px 0; font-size:14px; color:#6b7280;">
    The link works once and expires in <?= $e($view->expiry) ?>. If the button does not
    work, copy this address into your browser:<br>
    <span style="word-break:break-all;"><?= $e($view->url) ?></span>
</p>

<p style="margin:0; font-size:14px; color:#6b7280;">
    If you did not ask for this, you do not need to do anything: your password has not
    changed and will not change until somebody uses this link.
</p>
