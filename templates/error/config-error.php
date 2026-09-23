<?php

/**
 * Shown when the project's own configuration is broken — a syntax error, an
 * undeclared key, a bad {{env.*}} reference — rather than when a request
 * fails. Whoever reaches this page can fix the configuration, so it names
 * the problem outright; there is no debug flag to gate on here, because
 * nobody except the project's own developer ever sees it.
 *
 * @var array{message: string} $view
 * @var \Closure(mixed): string $e
 */
?>
<!doctype html>
<html class="ra-html" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuration error</title>
</head>
<body class="ra-error ra-error-config-error">
    <main class="ra-error-main">
        <h1 class="ra-error-title">Configuration error</h1>
        <p class="ra-error-message">RockAdmin cannot start until this is fixed:</p>
        <pre class="ra-error-detail"><?= $e($view['message']) ?></pre>
    </main>
</body>
</html>
