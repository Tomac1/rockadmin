<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;

#[CoversClass(Csrf::class)]
final class CsrfTest extends TestCase
{
    public function testTokenIsStableWithinASession(): void
    {
        $csrf = new Csrf(new ArraySessionStore());

        $this->assertSame($csrf->token(), $csrf->token());
    }

    public function testTokenIsLongAndHexadecimal(): void
    {
        $token = (new Csrf(new ArraySessionStore()))->token();

        $this->assertSame(64, \strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testDifferentSessionsGetDifferentTokens(): void
    {
        $first = (new Csrf(new ArraySessionStore()))->token();
        $second = (new Csrf(new ArraySessionStore()))->token();

        $this->assertNotSame($first, $second);
    }

    public function testValidation(): void
    {
        $csrf = new Csrf(new ArraySessionStore());
        $token = $csrf->token();

        $this->assertTrue($csrf->isValid($token));
        $this->assertFalse($csrf->isValid('wrong'));
        $this->assertFalse($csrf->isValid(null));
        $this->assertFalse($csrf->isValid(''));
    }

    public function testValidationFailsWhenNoTokenWasIssued(): void
    {
        $session = new ArraySessionStore();

        $this->assertFalse((new Csrf($session))->isValid('anything'));
    }
}
