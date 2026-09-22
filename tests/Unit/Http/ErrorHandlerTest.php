<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ErrorHandler;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\HttpException;
use RockAdmin\Http\NotFoundException;

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
}
