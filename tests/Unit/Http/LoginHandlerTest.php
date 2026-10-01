<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\LoginResult;
use RockAdmin\Form\FormFields;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\ForbiddenException;
use RockAdmin\Http\LoginHandler;
use RockAdmin\Http\LogoutHandler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Tests\Support\FakeAuthenticator;
use RockAdmin\Tests\Support\RecordingSessionStore;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The three routes a person meets before they are anybody: drawing the form,
 * submitting it, and leaving.
 *
 * What the drawn form looks like is `LoginTemplatesTest`'s question. These
 * tests are about the decisions only the handler makes — what a refusal costs,
 * what a success grants, where the person lands afterwards, and what the
 * session holds at each point.
 */
#[CoversClass(LoginHandler::class)]
#[CoversClass(LogoutHandler::class)]
final class LoginHandlerTest extends TestCase
{
    private const string PASSWORD = 'correct-horse-battery-staple';

    private RecordingSessionStore $session;

    private FakeAuthenticator $authenticator;

    private Identity $identity;

    private Csrf $csrf;

    private UrlGenerator $urls;

    protected function setUp(): void
    {
        $this->session = new RecordingSessionStore();
        $this->authenticator = new FakeAuthenticator();
        $this->identity = new Identity($this->session, $this->authenticator);
        $this->csrf = new Csrf($this->session);
        $this->urls = new UrlGenerator('/admin');
    }

    // ------------------------------------------------------------ drawing it

    public function testGetDrawsTheFormWithACsrfToken(): void
    {
        $response = $this->get();

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString($this->csrf->token(), $response->body);
        $this->assertStringContainsString('name="' . Csrf::FIELD . '"', $response->body);
    }

    public function testSomebodyAlreadySignedInIsSentOnRatherThanShownTheFormAgain(): void
    {
        $this->signedInAs('7');

        $response = $this->get();

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/', $response->headers['location'] ?? null);
    }

    public function testTheAddressTheKernelRememberedReachesTheFormAsAHiddenField(): void
    {
        $this->identity->rememberIntended('/admin/p/ads?grid%5Bpage%5D=3');

        $response = $this->get();

        $this->assertStringContainsString(
            'name="' . FormFields::RETURN_TO . '" value="/admin/p/ads?grid%5Bpage%5D=3"',
            $response->body,
        );
    }

    // -------------------------------------------------------------- refusing

    public function testAWrongPasswordRedrawsAtTwoHundredWithAMessageAndNoSession(): void
    {
        $this->authenticator->nextResult = LoginResult::refused();

        $response = $this->post(['email' => 'ada@example.com', 'password' => 'wrong']);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString(LoginResult::REFUSED, $response->body);
        $this->assertNull($this->session->get(Identity::SESSION_KEY));
        $this->assertSame(0, $this->session->regenerations);
    }

    public function testARefusalRedrawsTheEmailAndNeverThePassword(): void
    {
        $this->authenticator->nextResult = LoginResult::refused();

        $response = $this->post(['email' => 'ada@example.com', 'password' => self::PASSWORD]);

        $this->assertStringContainsString('value="ada@example.com"', $response->body);
        $this->assertStringNotContainsString(self::PASSWORD, $response->body);
    }

    public function testAThrottledAttemptIsDistinguishableInWhatTheHandlerDoesButNotInWhatItSays(): void
    {
        $this->authenticator->nextResult = LoginResult::throttled();
        $throttled = $this->post(['email' => 'ada@example.com', 'password' => 'wrong']);

        $this->setUp();
        $this->authenticator->nextResult = LoginResult::refused();
        $refused = $this->post(['email' => 'ada@example.com', 'password' => 'wrong']);

        // 429 against 200: the status is the difference a client can act on.
        $this->assertSame(429, $throttled->status);
        $this->assertSame(200, $refused->status);

        // The sentence still names neither limit.
        $this->assertStringContainsString(LoginResult::THROTTLED, $throttled->body);
        $this->assertStringNotContainsString('address', $throttled->body);
        $this->assertStringNotContainsString(' IP', $throttled->body);
    }

    public function testAThrottledAttemptSaysTheLockClearsByItself(): void
    {
        $this->authenticator->nextResult = LoginResult::throttled();

        $response = $this->post(['email' => 'ada@example.com', 'password' => 'wrong']);

        $this->assertStringContainsString(LoginHandler::THROTTLE_HINT, $response->body);
    }

    public function testARequestWithoutAValidTokenRedrawsTheFormRatherThanAnErrorPage(): void
    {
        $response = $this->post(['email' => 'ada@example.com', 'password' => 'x'], token: 'not-the-token');

        $this->assertSame(403, $response->status);
        $this->assertStringContainsString('name="' . Csrf::FIELD . '"', $response->body);
        $this->assertSame([], $this->authenticator->attempts, 'nothing was even attempted');
    }

    // ------------------------------------------------------------ succeeding

    public function testACorrectPasswordRedirectsAndSetsTheSession(): void
    {
        $this->authenticator->nextResult = LoginResult::success($this->ada());

        $response = $this->post(['email' => 'ada@example.com', 'password' => self::PASSWORD]);

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/', $response->headers['location'] ?? null);
        $this->assertSame('7', $this->session->get(Identity::SESSION_KEY));
    }

    public function testTheSessionIdChangesAcrossASuccessfulLogin(): void
    {
        $this->authenticator->nextResult = LoginResult::success($this->ada());
        $before = $this->session->id;

        $this->post(['email' => 'ada@example.com', 'password' => self::PASSWORD]);

        $this->assertNotSame($before, $this->session->id, 'a token fixed before the login must not survive it');
    }

    public function testTheAttemptCarriesTheAddressTheHostHandedTheHandler(): void
    {
        $this->authenticator->nextResult = LoginResult::refused();

        $this->post(['email' => ' Ada@Example.com ', 'password' => 'wrong']);

        $this->assertSame([[' Ada@Example.com ', 'wrong', '198.51.100.7']], $this->authenticator->attempts);
    }

    public function testSigningInStraightFromTheLoginPageLandsOnTheDashboard(): void
    {
        $this->authenticator->nextResult = LoginResult::success($this->ada());

        $response = $this->post(['email' => 'ada@example.com', 'password' => self::PASSWORD]);

        $this->assertSame($this->urls->route('dashboard'), $response->headers['location'] ?? null);
    }

    public function testAReturnAddressInsideTheAdminIsHonoured(): void
    {
        $this->authenticator->nextResult = LoginResult::success($this->ada());

        $response = $this->post([
            'email' => 'ada@example.com',
            'password' => self::PASSWORD,
            FormFields::RETURN_TO => '/admin/p/ads?grid%5Bpage%5D=3',
        ]);

        $this->assertSame('/admin/p/ads?grid%5Bpage%5D=3', $response->headers['location'] ?? null);
    }

    /** @return iterable<string, array{mixed}> */
    public static function addressesThatLeaveTheAdmin(): iterable
    {
        yield 'another site' => ['https://evil.example/steal'];
        yield 'protocol relative' => ['//evil.example/steal'];
        yield 'a header break' => ["/admin/p/ads\r\nLocation: https://evil.example"];
        yield 'outside the mount point' => ['/somewhere/else'];
        yield 'a dot segment' => ['/admin/../etc/passwd'];
        yield 'not a string at all' => [['/admin/p/ads']];
        yield 'empty' => [''];
    }

    #[DataProvider('addressesThatLeaveTheAdmin')]
    public function testAReturnAddressThatLeavesTheAdminIsDiscardedAndThePersonStillLandsSomewhereValid(mixed $address): void
    {
        $this->authenticator->nextResult = LoginResult::success($this->ada());

        $response = $this->post([
            'email' => 'ada@example.com',
            'password' => self::PASSWORD,
            FormFields::RETURN_TO => $address,
        ]);

        $location = $response->headers['location'] ?? null;

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/', $location);
    }

    public function testTheRememberedAddressIsUsedWhenTheBodyCarriesNoneAndIsThenForgotten(): void
    {
        $this->identity->rememberIntended('/admin/p/ads');
        $this->authenticator->nextResult = LoginResult::success($this->ada());

        $response = $this->post(['email' => 'ada@example.com', 'password' => self::PASSWORD]);

        $this->assertSame('/admin/p/ads', $response->headers['location'] ?? null);
        $this->assertNull($this->identity->intended(), 'a used return address is not kept for the next sign-in');
    }

    // ---------------------------------------------------------------- leaving

    public function testLoggingOutForgetsTheSessionAndRedirectsToLogin(): void
    {
        $this->signedInAs('7');

        $response = $this->logout();

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['location'] ?? null);
        $this->assertNull($this->session->get(Identity::SESSION_KEY));
        $this->assertSame('7', $this->authenticator->loggedOut?->id);
    }

    public function testLoggingOutReissuesTheSessionId(): void
    {
        $this->signedInAs('7');
        $before = $this->session->id;

        $this->logout();

        $this->assertNotSame($before, $this->session->id);
    }

    public function testLoggingOutNeedsATokenLikeEveryOtherPost(): void
    {
        $this->signedInAs('7');

        try {
            $this->logout(token: 'not-the-token');
            $this->fail('a logout without a valid token should be refused');
        } catch (ForbiddenException $e) {
            $this->assertStringContainsString('CSRF', $e->getMessage());
        }

        $this->assertSame('7', $this->session->get(Identity::SESSION_KEY), 'and the person stays signed in');
    }

    // ----------------------------------------------------------------- wiring

    private function ada(): AuthUser
    {
        return new AuthUser('7', 'ada@example.com', 'Ada');
    }

    private function signedInAs(string $id): void
    {
        $this->authenticator = new FakeAuthenticator([$id => $this->ada()]);
        $this->identity = new Identity($this->session, $this->authenticator);
        $this->session->set(Identity::SESSION_KEY, $id);
    }

    private function handler(): LoginHandler
    {
        return new LoginHandler(
            $this->authenticator,
            $this->identity,
            $this->csrf,
            new Renderer(new TemplateResolver([]), new Escaper(), $this->urls),
            new Assets($this->urls),
            $this->urls,
            'Demo Admin',
            '198.51.100.7',
        );
    }

    private function get(): Response
    {
        return $this->handler()->handle(new Route('login.form'), new Request('GET', 'login'));
    }

    /** @param array<string, mixed> $body */
    private function post(array $body, ?string $token = null): Response
    {
        $body[Csrf::FIELD] = $token ?? $this->csrf->token();

        return $this->handler()->handle(new Route('login.submit'), new Request('POST', 'login', body: $body));
    }

    private function logout(?string $token = null): Response
    {
        $handler = new LogoutHandler($this->identity, $this->csrf, $this->urls);

        return $handler->handle(
            new Route('logout'),
            new Request('POST', 'logout', body: [Csrf::FIELD => $token ?? $this->csrf->token()]),
        );
    }
}
