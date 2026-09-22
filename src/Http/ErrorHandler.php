<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Closure;
use Throwable;

/**
 * Turns a throwable into a response.
 *
 * In development it says exactly what went wrong and where. In production it
 * says only the status: an error page is an easy place to leak a schema, a
 * path or a query to whoever reaches it.
 *
 * Production errors still have to be recorded somewhere, so this is also the
 * one place that sees every throwable. Where the record goes is configuration,
 * which arrives later; this class only provides the seam, the way SessionStore
 * keeps session handling out of the core.
 */
final class ErrorHandler
{
    /** @param (Closure(Throwable): void)|null $logger null means nothing is recorded */
    public function __construct(
        private readonly bool $debug = false,
        private readonly ?Closure $logger = null,
    ) {
    }

    public function toResponse(Throwable $e): Response
    {
        if ($this->logger !== null) {
            try {
                ($this->logger)($e);
            } catch (Throwable) {
                // A logger that fails must not replace the throwable it was
                // called to record: reporting "the log disk is full" instead of
                // the actual error is worse than losing the log line. There is
                // nowhere left to report the logger's own failure, so it is
                // dropped deliberately.
            }
        }

        $status = $e instanceof HttpException ? $e->status : 500;

        return Response::html($this->body($e, $status), $status)
            // An error page is never worth caching, and a sniffed content type
            // on a page that may quote user input is worth even less.
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    private function body(Throwable $e, int $status): string
    {
        $title = match ($status) {
            403 => 'Forbidden',
            404 => 'Not found',
            default => 'Something went wrong',
        };

        $detail = '';

        // ENT_SUBSTITUTE, because without it a single invalid UTF-8 byte —
        // routine when a database exception quotes a raw column value — makes
        // htmlspecialchars() return the empty string, blanking the very detail
        // debug mode exists to show.
        if ($this->debug) {
            $detail = \sprintf(
                '<pre>%s: %s%sin %s:%d</pre>',
                htmlspecialchars($e::class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                PHP_EOL,
                htmlspecialchars($e->getFile(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $e->getLine(),
            );
        }

        return \sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>%1$d %2$s</title></head><body class="ra-error ra-error-%1$d">'
            . '<h1>%1$d %2$s</h1>%3$s</body></html>',
            $status,
            htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $detail,
        );
    }
}
