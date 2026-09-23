<?php

/**
 * Shown for anything not already named by a more specific status. This is
 * also the fallback TemplateErrorPage renders for a status with no template
 * of its own, so it is the one error page that must never itself be missing.
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
<body class="ra-error ra-error-500">
    <main class="ra-error-main">
        <h1 class="ra-error-title"><?= $e($view['status']) ?> <?= $e($view['title']) ?></h1>
        <p class="ra-error-message">Something went wrong. Try again, or contact support if it keeps happening.</p>
        <?php if ($view['debug']) { ?>
            <pre class="ra-error-detail"><?= $e($view['exceptionClass']) ?>: <?= $e($view['exceptionMessage']) ?>
in <?= $e($view['file']) ?>:<?= $e($view['line']) ?></pre>
        <?php } ?>
    </main>
</body>
</html>
