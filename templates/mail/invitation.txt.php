<?php
/** @var \RockAdmin\View\MailView $view */
?>
<?= $view->title ?>


Hello <?= $view->recipient ?>,

An administrator account has been created for you in <?= $view->brand ?>.
Choose a password to start using it:

<?= $view->url ?>


The link works once and expires in <?= $view->expiry ?>.

If you were not expecting this, ask whoever runs <?= $view->brand ?> about
it before using the link.
