<?php

/**
 * The plain-text shell.
 *
 * Written as text somebody would have typed, because that is the half that
 * reaches a terminal mail client, a screen reader set to plain text, and
 * anybody whose client blocks HTML. Lines are kept short enough to survive a
 * client that does not wrap, and the link stands on a line of its own so that
 * it can be copied without picking up a full stop.
 *
 * @var \RockAdmin\View\MailView $view
 */
?>
<?= $view->body ?>


--
<?= $view->brand ?>


This message was sent by <?= $view->brand ?>. If you were not expecting it,
nothing has happened yet and you can ignore it.
