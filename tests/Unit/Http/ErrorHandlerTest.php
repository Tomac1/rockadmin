<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\ErrorPage;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\HttpException;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateErrorPage;
use RockAdmin\View\TemplateResolver;

#[CoversClass(ErrorHandler::class)]
#[CoversClass(HttpException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(ForbiddenException::class)]
final class ErrorHandlerTest extends TestCase
{
    public function testHttpExceptionKeepsItsStatus(): void
    {
        $response = (new ErrorHandler())->toResponse(new NotFoundException('no such page'));

        $this->assertSame(404, $response->status);
        $this->assertSame(403, (new ErrorHandler())->toResponse(new ForbiddenException('nope'))->status);
    }

    public function testAnyOtherThrowableBecomes500(): void
    {
        $response = (new ErrorHandler())->toResponse(new \LogicException('boom'));

        $this->assertSame(500, $response->status);
    }

    public function testProductionHidesTheDetails(): void
    {
        $response = (new ErrorHandler(debug: false))
            ->toResponse(new \LogicException('SQLSTATE secret table ra_users'));

        $this->assertStringNotContainsString('secret', $response->body);
        $this->assertStringNotContainsString('LogicException', $response->body);
        $this->assertStringContainsString('500', $response->body);
    }

    public function testDebugShowsTheDetails(): void
    {
        $response = (new ErrorHandler(debug: true))->toResponse(new \LogicException('boom'));

        $this->assertStringContainsString('LogicException', $response->body);
        $this->assertStringContainsString('boom', $response->body);
        $this->assertStringContainsString(__FILE__, $response->body);
    }

    public function testDebugOutputIsEscaped(): void
    {
        $response = (new ErrorHandler(debug: true))
            ->toResponse(new \LogicException('<script>alert(1)</script>'));

        $this->assertStringNotContainsString('<script>', $response->body);
        $this->assertStringContainsString('&lt;script&gt;', $response->body);
    }

    public function testResponseIsHtml(): void
    {
        $response = (new ErrorHandler())->toResponse(new NotFoundException('x'));

        $this->assertSame('text/html; charset=utf-8', $response->headers['content-type']);
    }

    public function testErrorResponsesAreNeitherCachedNorSniffed(): void
    {
        $response = (new ErrorHandler())->toResponse(new NotFoundException('x'));

        $this->assertSame('no-store', $response->headers['cache-control']);
        $this->assertSame('nosniff', $response->headers['x-content-type-options']);
    }

    public function testAnInvalidUtf8ByteStillProducesADetail(): void
    {
        // Without ENT_SUBSTITUTE, htmlspecialchars() returns '' for this.
        $response = (new ErrorHandler(debug: true))->toResponse(new \LogicException("bad \xC3\x28 byte"));

        $this->assertStringContainsString('LogicException', $response->body);
        $this->assertStringContainsString('byte', $response->body);
        $this->assertStringContainsString('<pre>', $response->body);
    }

    public function testTheLoggerSeesEveryThrowableExactlyOnce(): void
    {
        $seen = [];
        $exception = new \LogicException('boom');

        $handler = new ErrorHandler(debug: false, logger: static function (\Throwable $e) use (&$seen): void {
            $seen[] = $e;
        });

        $handler->toResponse($exception);

        $this->assertCount(1, $seen);
        $this->assertSame($exception, $seen[0]);
    }

    public function testTheLoggerAlsoRunsInDebugModeAndForHttpExceptions(): void
    {
        $seen = 0;

        $handler = new ErrorHandler(debug: true, logger: static function (\Throwable $e) use (&$seen): void {
            ++$seen;
        });

        $handler->toResponse(new NotFoundException('no such page'));

        $this->assertSame(1, $seen);
    }

    public function testNoLoggerIsStillSafe(): void
    {
        $response = (new ErrorHandler())->toResponse(new \LogicException('boom'));

        $this->assertSame(500, $response->status);
    }

    public function testAFailingLoggerDoesNotReplaceTheThrowableItWasRecording(): void
    {
        $handler = new ErrorHandler(
            debug: true,
            logger: static fn (\Throwable $e): never => throw new \RuntimeException('the log disk is full'),
        );

        $response = $handler->toResponse(new NotFoundException('no such page'));

        // The original throwable still decides the status and the body; the
        // logger's own failure is dropped rather than reported in its place.
        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('no such page', $response->body);
        $this->assertStringNotContainsString('log disk', $response->body);
    }

    public function testAnErrorPageUsesTheTemplateWhenOneIsAvailable(): void
    {
        $page = new TemplateErrorPage(new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin')));

        $response = (new ErrorHandler(page: $page))->toResponse(new NotFoundException('no such page'));

        // Both the template and the built-in fallback carry "ra-error-404" —
        // it is "ra-error-main" that only the template writes.
        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('ra-error-main', $response->body);
    }

    public function testAnErrorPageFallsBackToTheBuiltInHtmlWithoutAnErrorPage(): void
    {
        $response = (new ErrorHandler())->toResponse(new NotFoundException('no such page'));

        $this->assertSame(404, $response->status);
        $this->assertStringNotContainsString('ra-error-main', $response->body);
        $this->assertStringContainsString('404', $response->body);
    }

    public function testAnErrorPageThatThrowsStillProducesTheOriginalErrorPage(): void
    {
        // A page that cannot render is still an error page: a second
        // exception here would replace the first one nobody has read yet.
        $page = new class () implements ErrorPage {
            public function render(\Throwable $error, int $status, bool $debug): ?string
            {
                throw new \RuntimeException('the template blew up');
            }
        };

        $response = (new ErrorHandler(page: $page))->toResponse(new NotFoundException('no such page'));

        // The built-in body, not the message: the built-in HTML never shows
        // an exception message outside debug mode either.
        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('Not found', $response->body);
        $this->assertStringNotContainsString('template blew up', $response->body);
    }

    public function testAnErrorPageWhoseResolverHasNoMatchingTemplateFallsBackToo(): void
    {
        // A resolver pointed at an empty directory: neither error/404 nor
        // error/500 exist, so TemplateErrorPage returns null rather than
        // throwing, and ErrorHandler falls back exactly as with no page.
        $empty = sys_get_temp_dir() . '/rockadmin-error-handler-test-' . uniqid();
        mkdir($empty);

        try {
            $page = new TemplateErrorPage(new Renderer(new TemplateResolver([$empty], $empty), new Escaper(), new UrlGenerator('/admin')));

            $response = (new ErrorHandler(page: $page))->toResponse(new NotFoundException('no such page'));

            $this->assertSame(404, $response->status);
            $this->assertStringContainsString('Not found', $response->body);
        } finally {
            rmdir($empty);
        }
    }
}
