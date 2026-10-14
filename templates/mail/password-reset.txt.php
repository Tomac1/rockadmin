<?php
/** @var \RockAdmin\View\MailView $view */
?>
<?= $view->title ?>


Hello <?= $view->recipient ?>,

Somebody asked to set a new password for your administrator account.
If it was you, open this address:

<?= $view->url ?>


The link works once and expires in <?= $view->expiry ?>.

If you did not ask for this, you do not need to do anything: your
password has not changed and will not change until somebody uses
this link.
