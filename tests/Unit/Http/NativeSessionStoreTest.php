<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\NativeSessionStore;

/**
 * The cookie a superadmin's login travels in.
 *
 * A bare `session_start()` gave `PHPSESSID` on path `/`, readable by script,
 * sent over plain HTTP, with strict mode off. The options are checked here
 * as values, because starting a real session in a test runner is not
 * possible after output has begun.
 */
#[CoversClass(NativeSessionStore::class)]
final class NativeSessionStoreTest extends TestCase
{
    public function testTheCookieIsHardenedByDefault(): void
    {
        $options = (new NativeSessionStore())->options();

        self::assertSame('rockadmin', $options['name']);
        self::assertTrue($options['cookie_httponly']);
        self::assertSame('Lax', $options['cookie_samesite']);
        self::assertTrue($options['use_strict_mode']);
        self::assertTrue($options['use_only_cookies']);
        self::assertFalse($options['use_trans_sid']);
    }

    public function testTheCookieIsLimitedToTheMountPointAndSecureOverHttps(): void
    {
        $options = (new NativeSessionStore('acme_admin', '/admin', 'Strict', true))->options();

        self::assertSame('acme_admin', $options['name']);
        self::assertSame('/admin', $options['cookie_path']);
        self::assertSame('Strict', $options['cookie_samesite']);
        self::assertTrue($options['cookie_secure']);
    }

    public function testSameSiteNoneIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('session.same_site');

        new NativeSessionStore(sameSite: 'None');
    }

    public function testACookieNameThatIsNotOneIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('session.name');

        new NativeSessionStore(name: 'my admin; path=/');
    }

    public function testHttpsIsReadFromTheServerOrAForwardingProxy(): void
    {
        self::assertTrue(NativeSessionStore::isHttps(['HTTPS' => 'on']));
        self::assertFalse(NativeSessionStore::isHttps(['HTTPS' => 'off']));
        self::assertFalse(NativeSessionStore::isHttps([]));
        self::assertTrue(NativeSessionStore::isHttps(['HTTP_X_FORWARDED_PROTO' => 'https']));
        self::assertTrue(NativeSessionStore::isHttps(['HTTP_X_FORWARDED_PROTO' => 'https, http']));
        self::assertFalse(NativeSessionStore::isHttps(['HTTP_X_FORWARDED_PROTO' => 'http']));
    }
}
