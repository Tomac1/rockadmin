<?php

/**
 * The shell every HTML message is drawn in.
 *
 * **It is not the design system, and that is deliberate.** `docs/design-system/`
 * is the authority on how the admin looks, and none of it survives a mail
 * client: there is no external stylesheet, no custom property, no flexbox worth
 * relying on, and a `<style>` block is stripped by some of the clients that
 * matter. So this is a table, inline styles, and web-safe colours -- the subset
 * that renders the same in Outlook as in Gmail -- and it deliberately looks
 * plainer than the admin rather than looking broken in half the places it
 * arrives.
 *
 * **No images and no tracking pixel.** An image is blocked by default in most
 * clients, so a message whose branding is an image is a message that arrives
 * blank; and a pixel in a password reset would be a record of when somebody
 * read their mail, kept by an admin that has no business knowing.
 *
 * @var \RockAdmin\View\MailView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $raw
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($view->title) ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Helvetica,Arial,sans-serif; color:#1f2430;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e3e6ea; border-radius:6px;">
                    <tr>
                        <td style="padding:24px 28px 8px 28px; font-size:14px; line-height:1.5; color:#6b7280;">
                            <?= $e($view->brand) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 24px 28px; font-size:16px; line-height:1.6;">
                            <?= $raw($view->body) ?>
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                    <tr>
                        <td style="padding:16px 28px; font-size:12px; line-height:1.5; color:#6b7280;">
                            This message was sent by <?= $e($view->brand) ?>. If you were not expecting it,
                            nothing has happened yet and you can ignore it.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
