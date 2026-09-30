<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\Authenticator;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Identity;
use RockAdmin\Http\SessionStore;
use RockAdmin\Tests\Support\FakeAuthenticator;
use RockAdmin\Tests\Support\RecordingSessionStore;

/**
 * Step 3 of the lifecycle: who is asking.
 *
 * `Identity` joins the two seams a host may replace — `SessionStore` and
 * `Authenticator` — and owns the one session key that says who is signed in.
 * Most of what follows is about what happens when that key holds something
 * the authenticator will not honour, because that is the case a live
 * installation actually produces: a session outliving a deleted account, a
 * deactivated one, or a cookie somebody edited.
 */
#[CoversClass(Identity::class)]
final class IdentityTest extends TestCase
{
    private function user(string $id = '7'): AuthUser
    {
        return new AuthUser($id, 'jana@example.test', 'Jana', ['editor']);
    }

    private function identity(SessionStore $session, Authenticator $auth): Identity
    {
        return new Identity($session, $auth);
    }

    public function testNoSessionIsAnonymous(): void
    {
        $identity = $this->identity(new RecordingSessionStore(), new FakeAuthenticator([]));

        $this->assertNull($identity->user());
        $this->assertFalse($identity->isAuthenticated());
    }

    public function testASessionNamingAKnownUserResolvesToThatUser(): void
    {
        $session = new RecordingSessionStore([Identity::SESSION_KEY => '7']);
        $identity = $this->identity($session, new FakeAuthenticator(['7' => $this->user()]));

        $user = $identity->user();

        $this->assertInstanceOf(AuthUser::class, $user);
        $this->assertSame('jana@example.test', $user->email);
        $this->assertTrue($identity->isAuthenticated());
    }

    public function testASessionNamingAUserThatNoLongerExistsIsAnonymousRatherThanACrash(): void
    {
        $session = new RecordingSessionStore([Identity::SESSION_KEY => '404']);

        $this->assertNull($this->identity($session, new FakeAuthenticator([]))->user());
    }

    public function testASessionNamingAUserThatNoLongerExistsIsForgotten(): void
    {
        $session = new RecordingSessionStore([Identity::SESSION_KEY => '404']);

        $this->identity($session, new FakeAuthenticator([]))->user();

        $this->assertNull($session->get(Identity::SESSION_KEY));
    }

    public function testATamperedSessionValueIsAnonymous(): void
    {
        $session = new RecordingSessionStore([Identity::SESSION_KEY => '7 OR 1=1']);

        $this->assertNull(
            $this->identity($session, new FakeAuthenticator(['7' => $this->user()]))->user(),
        );
    }

    public function testASessionValueThatIsNotAStringIsAnonymous(): void
    {
        $session = new RecordingSessionStore([Identity::SESSION_KEY => ['7']]);

        $this->assertNull(
            $this->identity($session, new FakeAuthenticator(['7' => $this->user()]))->user(),
        );
    }

    public function testTheUserIsResolvedOncePerRequest(): void
    {
        $session = new RecordingSessionStore([Identity::SESSION_KEY => '7']);
        $auth = new FakeAuthenticator(['7' => $this->user()]);
        $identity = $this->identity($session, $auth);

        $identity->user();
        $identity->user();
        $identity->isAuthenticated();

        $this->assertSame(1, $auth->lookups);
    }

    public function testAnAnonymousRequestIsNotLookedUpAtAll(): void
    {
        $auth = new FakeAuthenticator([]);
        $identity = $this->identity(new RecordingSessionStore(), $auth);

        $identity->user();
        $identity->user();

        $this->assertSame(0, $auth->lookups);
    }

    public function testLoggingInStoresTheIdAndRegeneratesTheSession(): void
    {
        $session = new RecordingSessionStore();
        $identity = $this->identity($session, new FakeAuthenticator(['7' => $this->user()]));

        $identity->login($this->user());

        $this->assertSame('7', $session->get(Identity::SESSION_KEY));
        $this->assertSame(1, $session->regenerations);
        $this->assertTrue($identity->isAuthenticated());
    }

    public function testLoggingOutForgetsTheSessionAndTellsTheAuthenticator(): void
    {
        $session = new RecordingSessionStore([Identity::SESSION_KEY => '7']);
        $auth = new FakeAuthenticator(['7' => $this->user()]);

        $identity = $this->identity($session, $auth);
        $identity->logout();

        $this->assertNull($session->get(Identity::SESSION_KEY));
        $this->assertNull($identity->user());
        $this->assertInstanceOf(AuthUser::class, $auth->loggedOut);
        $this->assertSame(1, $session->regenerations);
    }

    public function testLoggingOutWhenNobodyIsSignedInDoesNothing(): void
    {
        $auth = new FakeAuthenticator([]);
        $identity = $this->identity(new RecordingSessionStore(), $auth);

        $identity->logout();

        $this->assertNull($identity->user());
        $this->assertNull($auth->loggedOut);
    }
}
