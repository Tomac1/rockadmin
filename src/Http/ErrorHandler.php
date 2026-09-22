<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Throwable;

/**
 * Turns a throwable into a response.
 *
 * In development it says exactly what went wrong and where. In production it
 * says only the status: an error page is an easy place to leak a schema, a
 * path or a query to whoever reaches it.
 */
final class ErrorHandler
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function toResponse(Throwable $e): Response
    {
        $status = $e instanceof HttpException ? $e->status : 500;

        return Response::html($this->body($e, $status), $status);
    }

    private function body(Throwable $e, int $status): string
    {
        $title = match ($status) {
            403 => 'Forbidden',
            404 => 'Not found',
            default => 'Something went wrong',
        };

        $detail = '';

        if ($this->debug) {
            $detail = \sprintf(
                '<pre>%s: %s%sin %s:%d</pre>',
                htmlspecialchars($e::class, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
                PHP_EOL,
                htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'),
                $e->getLine(),
            );
        }

        return \sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>%1$d %2$s</title></head><body class="ra-error ra-error-%1$d">'
            . '<h1>%1$d %2$s</h1>%3$s</body></html>',
            $status,
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $detail,
        );
    }
}
