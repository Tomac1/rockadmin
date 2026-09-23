<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Http\ErrorPage;
use Throwable;

/**
 * Renders an error page from the templates in error/.
 *
 * Resolves `error/{$status}`, falls back to `error/500`, and returns null
 * when neither exists — the signal `ErrorHandler` reads as "use your own
 * built-in HTML". Nothing here catches what rendering itself throws; that is
 * `ErrorHandler`'s job, because it is the one place that knows what to show
 * when the error page about an error fails.
 */
final class TemplateErrorPage implements ErrorPage
{
    public function __construct(private readonly Renderer $renderer)
    {
    }

    public function render(Throwable $error, int $status, bool $debug): ?string
    {
        $template = 'error/' . $status;

        if (!$this->renderer->templates()->has($template)) {
            $template = 'error/500';

            if (!$this->renderer->templates()->has($template)) {
                return null;
            }
        }

        return $this->renderer->render($template, [
            'status' => $status,
            'title' => $this->title($status),
            'debug' => $debug,
            'exceptionClass' => $debug ? $error::class : null,
            'exceptionMessage' => $debug ? $error->getMessage() : null,
            'file' => $debug ? $error->getFile() : null,
            'line' => $debug ? $error->getLine() : null,
        ]);
    }

    private function title(int $status): string
    {
        return match ($status) {
            403 => 'Forbidden',
            404 => 'Not found',
            default => 'Something went wrong',
        };
    }
}
