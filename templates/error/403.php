<?php

/**
 * Shown when the request is refused by a gate, not by a route that does not
 * exist. Rendered standalone, without the shell: the session that would
 * build a menu may be exactly what is missing here.
 *
 * @var array{status: int, title: string, debug: bool, exceptionClass: string|null, exceptionMessage: string|null, file: string|null, line: int|null} $view
 * @var \Closure(mixed): string $e
 */
?>
<!doctype html>
<html class="ra-html" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($view['status']) ?> <?= $e($view['title']) ?></title>
</head>
<body class="ra-error ra-error-403">
    <main class="ra-error-main">
        <h1 class="ra-error-title"><?= $e($view['status']) ?> <?= $e($view['title']) ?></h1>
        <p class="ra-error-message">You do not have permission to view this page.</p>
        <?php if ($view['debug']) { ?>
            <pre class="ra-error-detail"><?= $e($view['exceptionClass']) ?>: <?= $e($view['exceptionMessage']) ?>
in <?= $e($view['file']) ?>:<?= $e($view['line']) ?></pre>
        <?php } ?>
    </main>
</body>
</html>
