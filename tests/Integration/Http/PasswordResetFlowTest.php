<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\LoginOutcome;
use RockAdmin\Auth\PasswordResets;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Auth\Throttle;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Migrator;
use RockAdmin\Db\Sql;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\LoginHandler;
use RockAdmin\Http\PasswordResetHandler;
use RockAdmin\Http\Request;
use RockAdmin\Http\Response;
use RockAdmin\Http\Route;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\Mailer;
use RockAdmin\Mail\Message;
use RockAdmin\Mail\MessageFactory;
use RockAdmin\Tests\Support\DatabaseTestCase;
use RockAdmin\View\Assets;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * Forgotten password, the whole way round.
 *
 * The test that matters reads the link out of the message the way a person
 * reads it out of their inbox, follows it, sets a password and signs in with
 * it. Everything short of that -- a handler that issues a token, a store that
 * claims one -- can pass while the flow is broken in the one place nobody
 * looked: the URL in the mail.
 *
 * The rest is about what must *not* be visible. The answer to the request form
 * is the same for an address that exists and one that does not, and so is its
 * status code; a link works once; and a reset clears the lockout that may have
 * been the reason somebody wanted one.
 *
 * Both servers, with a real `ra_users` and a real `ra_password_resets`.
 */
#[CoversClass(PasswordResetHandler::class)]
#[CoversClass(PasswordResets::class)]
final class PasswordResetFlowTest extends DatabaseTestCase
{
    private const string ADDRESS = 'jana@example.com';

    private const string OLD_PASSWORD = 'the-old-password';

    private const string NEW_PASSWORD = 'a-brand-new-password';

    /** @var list<string> dropped in dependency order, children first */
    private const TABLES = [
        'ra_activity_log',
        'ra_password_resets',
        'ra_login_attempts',
        'ra_users',
        'ra_migrations',
    ];

    private SessionStore $session;

    /** @var list<Message> what the mailer was handed */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new ArraySessionStore();
        $this->sent = [];

        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->drop($connection);
                (new Migrator($connection))->migrate();
                (new AdminUsers($connection))->create(self::ADDRESS, 'Jana', self::OLD_PASSWORD, ['superadmin'], []);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (self::connections() as [$connection]) {
            if ($connection !== null) {
                $this->drop($connection);
                $this->createFixtures($connection);
            }
        }

        parent::tearDown();
    }

    /** The one that matters. */
    #[DataProvider('connections')]
    public function testSomebodyWhoForgotTheirPasswordGetsBackIn(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $asked = $this->ask($connection, self::ADDRESS);
        self::assertSame(200, $asked->status);

        self::assertCount(1, $this->sent, 'No message was sent to an address that exists.');

        $token = $this->tokenFromTheMessage();
        self::assertNotSame('', $token, 'The message carries no link anybody could follow.');

        $form = $this->open($connection, $token);
        self::assertSame(200, $form->status, 'The link did not open a form.');
        self::assertStringContainsString('New password', $form->body);

        $set = $this->submit($connection, $token, self::NEW_PASSWORD, self::NEW_PASSWORD);
        self::assertSame(302, $set->status, 'Setting the password did not redirect.');

        self::assertSame(
            LoginOutcome::Success,
            $this->signIn($connection, self::NEW_PASSWORD)->outcome,
            'The new password does not sign anybody in.',
        );
        self::assertSame(
            LoginOutcome::Refused,
            $this->signIn($connection, self::OLD_PASSWORD)->outcome,
            'The old password still works.',
        );
    }

    #[DataProvider('connections')]
    public function testTheLoginPageSaysThePasswordWasSet(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->ask($connection, self::ADDRESS);
        $this->submit($connection, $this->tokenFromTheMessage(), self::NEW_PASSWORD, self::NEW_PASSWORD);

        $login = $this->login($connection)->handle(
            new Route('login.form', []),
            new Request('GET', 'login'),
        );

        self::assertStringContainsString('Your password is set', $login->body);
    }

    /**
     * The disclosure this form exists to avoid. Both halves have to match: the
     * words and the status code.
     */
    #[DataProvider('connections')]
    public function testAnAddressThatDoesNotExistIsAnsweredExactlyTheSameWay(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $known = $this->ask($connection, self::ADDRESS);
        $unknown = $this->ask($connection, 'nobody@example.com');

        self::assertSame($known->status, $unknown->status);
        self::assertStringContainsString('If that address belongs to an administrator', $unknown->body);
        self::assertSame(
            $this->withoutToken($known->body),
            $this->withoutToken($unknown->body),
            'The two answers differ in something other than their CSRF token.',
        );

        self::assertCount(1, $this->sent, 'A message was sent to an address nobody has.');
    }

    #[DataProvider('connections')]
    public function testADeactivatedAdministratorIsAnsweredTheSameWayAndGetsNothing(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $connection->execute(new Sql('UPDATE ra_users SET is_active = ?', [false]));

        $response = $this->ask($connection, self::ADDRESS);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('If that address belongs to an administrator', $response->body);
        self::assertSame([], $this->sent, 'A deactivated account was sent a way back in.');
    }

    #[DataProvider('connections')]
    public function testALinkWorksOnceAndSaysSoAfterwards(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->ask($connection, self::ADDRESS);
        $token = $this->tokenFromTheMessage();

        $this->submit($connection, $token, self::NEW_PASSWORD, self::NEW_PASSWORD);
        $again = $this->submit($connection, $token, 'another-password-entirely', 'another-password-entirely');

        self::assertSame(404, $again->status);
        self::assertStringContainsString('expired or has already been used', $again->body);
        self::assertSame(
            LoginOutcome::Success,
            $this->signIn($connection, self::NEW_PASSWORD)->outcome,
            'The second use changed the password anyway.',
        );
    }

    #[DataProvider('connections')]
    public function testAMistypedConfirmationDoesNotSpendTheLink(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->ask($connection, self::ADDRESS);
        $token = $this->tokenFromTheMessage();

        $refused = $this->submit($connection, $token, self::NEW_PASSWORD, 'something-else-entirely');

        self::assertSame(422, $refused->status);
        self::assertStringContainsString('not the same', $refused->body);

        $second = $this->submit($connection, $token, self::NEW_PASSWORD, self::NEW_PASSWORD);

        self::assertSame(302, $second->status, 'A typo in the confirmation burned the link.');
    }

    #[DataProvider('connections')]
    public function testATooShortPasswordIsRefusedWithoutSpendingTheLink(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->ask($connection, self::ADDRESS);
        $token = $this->tokenFromTheMessage();

        $refused = $this->submit($connection, $token, 'short', 'short');

        self::assertSame(422, $refused->status);
        self::assertStringContainsString('at least', $refused->body);
        self::assertSame(302, $this->submit($connection, $token, self::NEW_PASSWORD, self::NEW_PASSWORD)->status);
    }

    #[DataProvider('connections')]
    public function testAnUnknownLinkOffersTheFormRatherThanAnError(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->open($connection, bin2hex(random_bytes(32)));

        self::assertSame(404, $response->status);
        self::assertStringContainsString('expired or has already been used', $response->body);
        self::assertStringContainsString('Type the address you sign in with', $response->body);
    }

    #[DataProvider('connections')]
    public function testASubmissionWithNoTokenIsRefusedAndSendsNothing(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->handler($connection)->handle(
            new Route('password.request.submit', []),
            new Request('POST', 'password/reset', body: [PasswordResetHandler::EMAIL_FIELD => self::ADDRESS]),
        );

        self::assertSame(403, $response->status);
        self::assertSame([], $this->sent);
    }

    #[DataProvider('connections')]
    public function testAskingTooOftenIsRefusedInWords(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        for ($i = 0; $i < 3; $i++) {
            $this->ask($connection, self::ADDRESS, perEmail: 3);
        }

        $refused = $this->ask($connection, self::ADDRESS, perEmail: 3);

        self::assertSame(429, $refused->status);
        self::assertStringContainsString('Too many requests', $refused->body);
        self::assertCount(3, $this->sent, 'A refused request still sent a message.');
    }

    /**
     * Somebody who asked for a reset because they were locked out must not
     * still be locked out once they have used it.
     */
    #[DataProvider('connections')]
    public function testSettingAPasswordClearsTheLoginLockout(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);
        $throttle = new Throttle($connection, perEmail: 3, perIp: 20);

        for ($i = 0; $i < 3; $i++) {
            $throttle->record(Throttle::LOGIN, self::ADDRESS, '10.0.0.1');
        }

        self::assertTrue($throttle->isExhausted(Throttle::LOGIN, self::ADDRESS, '10.0.0.1'));

        $this->ask($connection, self::ADDRESS);
        $this->submit($connection, $this->tokenFromTheMessage(), self::NEW_PASSWORD, self::NEW_PASSWORD);

        self::assertFalse(
            $throttle->isExhausted(Throttle::LOGIN, self::ADDRESS, '10.0.0.1'),
            'The lockout survived the reset that existed to end it.',
        );
    }

    /**
     * A link built from the `Host` header is a link an attacker chooses by
     * sending one. With no `public_url` the admin says so and sends nothing,
     * rather than sending a message whose link points somewhere else.
     */
    #[DataProvider('connections')]
    public function testWithNoPublicUrlNothingIsSentAndTheReasonIsPlain(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $response = $this->ask($connection, self::ADDRESS, publicUrl: '');

        self::assertSame(500, $response->status);
        self::assertStringContainsString('public_url', $response->body);
        self::assertSame([], $this->sent);
    }

    // --- harness ---

    private function ask(
        Connection $connection,
        string $email,
        int $perEmail = 10,
        string $publicUrl = 'https://admin.example.com',
    ): Response {
        return $this->handler($connection, $perEmail, $publicUrl)->handle(
            new Route('password.request.submit', []),
            new Request('POST', 'password/reset', body: [
                Csrf::FIELD => (new Csrf($this->session))->token(),
                PasswordResetHandler::EMAIL_FIELD => $email,
            ]),
        );
    }

    private function open(Connection $connection, string $token): Response
    {
        return $this->handler($connection)->handle(
            new Route('password.reset.form', ['token' => $token]),
            new Request('GET', "password/reset/{$token}"),
        );
    }

    private function submit(Connection $connection, string $token, string $password, string $confirm): Response
    {
        return $this->handler($connection)->handle(
            new Route('password.reset.submit', ['token' => $token]),
            new Request('POST', "password/reset/{$token}", body: [
                Csrf::FIELD => (new Csrf($this->session))->token(),
                PasswordResetHandler::PASSWORD_FIELD => $password,
                PasswordResetHandler::CONFIRM_FIELD => $confirm,
            ]),
        );
    }

    private function signIn(Connection $connection, string $password): \RockAdmin\Auth\LoginResult
    {
        return (new SqlAuthenticator($connection))->attempt(self::ADDRESS, $password, '10.0.0.77');
    }

    /** The link as somebody reads it out of their inbox, in the half that is text. */
    private function tokenFromTheMessage(): string
    {
        $message = $this->sent[\count($this->sent) - 1] ?? null;

        if ($message === null) {
            return '';
        }

        preg_match('#/password/reset/([a-f0-9]{64})#', $message->text, $found);

        return $found[1] ?? '';
    }

    private function handler(
        Connection $connection,
        int $perEmail = 10,
        string $publicUrl = 'https://admin.example.com',
    ): PasswordResetHandler {
        $urls = new UrlGenerator('/admin');
        $renderer = new Renderer(new TemplateResolver([]), new Escaper(), $urls);

        return new PasswordResetHandler(
            new AdminUsers($connection),
            new PasswordResets($connection),
            new Throttle($connection, $perEmail, 100),
            new Identity($this->session, new SqlAuthenticator($connection)),
            $this->mailer(),
            new MessageFactory($renderer, new Mailbox('admin@example.com', 'Admin'), 'RockAdmin'),
            new Csrf($this->session),
            $renderer,
            new Assets($urls),
            new FlashBag($this->session),
            $urls,
            'RockAdmin',
            '10.0.0.1',
            $publicUrl,
        );
    }

    private function login(Connection $connection): LoginHandler
    {
        $urls = new UrlGenerator('/admin');

        return new LoginHandler(
            new SqlAuthenticator($connection),
            new Identity($this->session, new SqlAuthenticator($connection)),
            new Csrf($this->session),
            new Renderer(new TemplateResolver([]), new Escaper(), $urls),
            new Assets($urls),
            $urls,
            'RockAdmin',
            '10.0.0.1',
            'auto',
            new FlashBag($this->session),
        );
    }

    /** A mailer that keeps what it was handed, which is what an inbox is here. */
    private function mailer(): Mailer
    {
        $test = $this;

        return new class ($test) implements Mailer {
            public function __construct(private readonly PasswordResetFlowTest $test)
            {
            }

            public function send(Message $message): void
            {
                $this->test->received($message);
            }
        };
    }

    public function received(Message $message): void
    {
        $this->sent[] = $message;
    }

    /**
     * The page without its CSRF token, so that two renderings can be compared
     * for anything *else* that differs.
     */
    private function withoutToken(string $body): string
    {
        return preg_replace('/value="[a-f0-9]{32,}"/', 'value="[token]"', $body) ?? $body;
    }

    private function drop(Connection $connection): void
    {
        foreach (self::TABLES as $table) {
            $connection->execute(new Sql("DROP TABLE IF EXISTS {$table}"));
        }
    }
}
