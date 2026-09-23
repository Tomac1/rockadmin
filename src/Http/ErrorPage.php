<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

/**
 * Renders a full error page for a throwable, or defers to the built-in one.
 *
 * `Http` sits below `View`: the kernel, the router and `ErrorHandler` must
 * keep working for a project that renders nothing at all, so nothing here
 * mentions a template or a renderer. The implementation that can do that is
 * `RockAdmin\View\TemplateErrorPage`.
 */
interface ErrorPage
{
    /** The rendered page, or null to leave the built-in HTML in place. */
    public function render(Throwable $error, int $status, bool $debug): ?string;
}
