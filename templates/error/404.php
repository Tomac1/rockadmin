<?php

/**
 * Shown when nothing matches the request. Rendered standalone, without the
 * shell: the page being asked for is exactly the thing that could not be
 * resolved, so there is nothing to build a menu against.
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
<body class="ra-error ra-error-404">
    <main class="ra-error-main">
        <h1 class="ra-error-title"><?= $e($view['status']) ?> <?= $e($view['title']) ?></h1>
        <p class="ra-error-message">The page you were looking for does not exist.</p>
        <?php if ($view['debug']) { ?>
            <pre class="ra-error-detail"><?= $e($view['exceptionClass']) ?>: <?= $e($view['exceptionMessage']) ?>
in <?= $e($view['file']) ?>:<?= $e($view['line']) ?></pre>
        <?php } ?>
    </main>
</body>
</html>
