<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\PasswordResets;
use RockAdmin\Auth\Throttle;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\Mailer;
use RockAdmin\Mail\MessageFactory;
use RockAdmin\View\Assets;
use RockAdmin\View\FlashBag;
use RockAdmin\View\PasswordResetView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;
use Throwable;

/**
 * `GET|POST /password/reset` and `/password/reset/{token}` — the four routes
 * specification 10 ships in v1.
 *
 * All four in one handler for the reason `LoginHandler` holds two: they are
 * one conversation, they share a form, a throttle and a token store, and a
 * second class would be a second place deciding what to say when an address
 * does not exist.
 *
 * **The answer never says whether the address exists.** "If that address
 * belongs to an administrator, we have sent a link" is the whole of it, for
 * every address, every time. Anything else turns this form into a list of who
 * works here -- and the ways to leak it are not only the message: a mail
 * failure that 500s for real addresses and renders for unknown ones is the
 * same disclosure with extra steps, which is why one is caught here.
 *
 * **Throttled by address and by machine**, under its own kind. Sharing the
 * login counter would mean ten forgotten passwords locking somebody out of the
 * login form, and a login lockout blocking the one self-service way out of
 * itself.
 *
 * **Setting a password does not sign anybody in.** A link that granted a
 * session would make the mailbox a second credential with no throttle, no
 * attempt record and no wrong-password counter behind it. It does clear that
 * address's login lockout, because otherwise somebody who reset their password
 * precisely because they had been locked out would still be locked out.
 *
 * **Two things this does not promise, said here rather than assumed.**
 *
 * *Sessions already open survive a reset.* Somebody who changes their password
 * because they think somebody else has it is not thereby signing that person
 * out. Specification 9.1 is explicit about what "logout everywhere" needs
 * before it can be built honestly -- a `sessions_valid_from` column stamped at
 * the change, and a `SessionStore` that records when a session began, which it
 * does not -- and equally explicit that stamping a column nothing reads would
 * look exactly like the feature while every other session stayed live. So it
 * is v1.1, and until then this says so instead of implying otherwise.
 *
 * *The time a request takes still differs.* The words, the status code and the
 * page are identical for an address that exists and one that does not, and the
 * test compares the renderings to keep them so. What cannot be equalised here
 * is the work: an address that exists costs a token, a rendered message and a
 * send, and an SMTP send is measured in hundreds of milliseconds. Equalising it
 * properly means doing the send out of band -- a queue, or a response flushed
 * before the work, neither of which exists in an SDK with no dependencies and
 * no control over the host's SAPI. What stands between that signal and a
 * usable oracle is the throttle: ten requests per address and twenty per
 * machine per quarter hour, which is not a rate anybody enumerates a staff
 * list at. It is a residual rather than a defence, and it is written down.
 */
final class PasswordResetHandler implements Handler
{
    public const string EMAIL_FIELD = 'email';

    public const string PASSWORD_FIELD = 'password';

    public const string CONFIRM_FIELD = 'password_confirm';

    /**
     * How short a new password may be.
     *
     * Ten rather than eight, and no composition rules at all. A length floor
     * is the one requirement that reliably buys entropy; "one capital, one
     * digit, one symbol" buys `Password1!` and a note on a monitor. The
     * control carries `minlength` as well, so the browser says so before the
     * round trip -- and this checks it again, because the browser is not where
     * the rule lives.
     */
    public const int MINIMUM_LENGTH = 10;

    private const string CONSTANT_ANSWER = 'If that address belongs to an administrator, a link to '
        . 'set a new password is on its way to it. The link works once and expires.';

    private const string STALE_FORM = 'This form was open too long and could not be checked. Try again.';

    private const string DEAD_LINK = 'That link has expired or has already been used. Ask for another '
        . 'one and use the newest mail.';

    public function __construct(
        private readonly AdminUsers $users,
        private readonly PasswordResets $resets,
        private readonly Throttle $throttle,
        private readonly Identity $identity,
        private readonly Mailer $mailer,
        private readonly MessageFactory $messages,
        private readonly Csrf $csrf,
        private readonly Renderer $renderer,
        private readonly Assets $assets,
        private readonly FlashBag $flashes,
        private readonly UrlGenerator $urls,
        private readonly string $brand,
        private readonly string $clientIp,
        /**
         * Where this admin is reachable from outside, from `public_url`.
         *
         * Not derived from the request, and that is the point: a link built
         * from the `Host` header is a link an attacker chooses by sending one,
         * which turns this form into a way of collecting reset tokens -- the
         * mail goes to the right person and the link points at the wrong
         * server. Empty means no link can be built, and the form says so
         * rather than sending a message nobody can use.
         */
        private readonly string $publicUrl = '',
        private readonly int $lifetimeMinutes = 60,
        private readonly string $darkMode = 'auto',
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        if ($this->identity->isAuthenticated()) {
            // Somebody already signed in has no business here: the page would
            // let them set a password for whichever account a link names,
            // which is not the account they are signed in as.
            return Response::redirect($this->urls->route('dashboard'));
        }

        return match ($route->name) {
            'password.request.form' => $this->drawRequest(),
            'password.request.submit' => $this->request($request),
            'password.reset.form' => $this->drawSet($route->param('token')),
            'password.reset.submit' => $this->set($route->param('token'), $request),
            default => throw new NotFoundException("Route '{$route->name}' is not a password route."),
        };
    }

    /**
     * Somebody asking for a link.
     *
     * The order is deliberate: token, then throttle, then record, then the
     * lookup. A request that cannot prove where it came from does not get to
     * fill a throttle counter for an address somebody else owns, and the
     * attempt is written down before anything is read so that a later failure
     * cannot take the evidence with it.
     */
    private function request(Request $request): Response
    {
        $email = $this->text($request->input(self::EMAIL_FIELD));

        if (!$this->csrf->isValid($this->token($request))) {
            return $this->drawRequest($email, self::STALE_FORM, status: 403);
        }

        if ($this->throttle->isExhausted(Throttle::PASSWORD_RESET, $email, $this->clientIp)) {
            // Refused in words, because the person is not the attacker in the
            // common case -- and because the constant answer would be a lie
            // here: no mail is going anywhere.
            return $this->drawRequest($email, \sprintf(
                'Too many requests for a reset link. Wait %d minutes and try again.',
                $this->throttle->windowMinutes(),
            ), status: 429);
        }

        $this->throttle->record(Throttle::PASSWORD_RESET, $email, $this->clientIp);

        if ($this->publicUrl === '') {
            // Said plainly rather than constantly: this is a misconfiguration
            // of the installation and not a fact about the address, so saying
            // so leaks nothing and saves whoever is staring at a mail that
            // never came.
            return $this->drawRequest($email, 'This admin cannot build a link, because public_url '
                . 'is not set in rockadmin.php. Nothing has been sent; tell whoever administers this '
                . 'installation.', status: 500);
        }

        $user = $this->users->activeByEmail($email);

        if ($user !== null) {
            $this->send($user['email'], $user['name']);
        }

        return $this->drawRequest(notice: self::CONSTANT_ANSWER);
    }

    /**
     * Issues the token and sends the message.
     *
     * A failure here is swallowed on purpose and that is worth being explicit
     * about: a broken mailer that 500s only for addresses that exist is an
     * enumeration oracle, so the response cannot change. What it must not do
     * is disappear, so the reason is appended to the admin's own mail log --
     * the one place that already holds what was sent.
     */
    private function send(string $email, string $name): void
    {
        try {
            $token = $this->resets->issue($email);

            $this->mailer->send($this->messages->passwordReset(
                new Mailbox($email, $name),
                rtrim($this->publicUrl, '/') . $this->urls->route('password.reset.form', ['token' => $token]),
                $this->lifetimeMinutes,
            ));
        } catch (Throwable $e) {
            // Nothing is flashed, and that is the fix for a leak this code had
            // for one commit. A flash lands in the session of whoever asked,
            // and the login page they are sent to next draws one -- so "a
            // password reset could not be sent" would appear for addresses
            // that exist and not for addresses that do not, which is the
            // disclosure this whole handler is shaped around, arriving by the
            // back door. The failure goes where only the installation can see
            // it.
            error_log('RockAdmin: a password reset for an administrator could not be sent: ' . $e->getMessage());
        }
    }

    /** The form behind a link, drawn only while the link is still good. */
    private function drawSet(string $token): Response
    {
        if (!$this->resets->isLive($token)) {
            return $this->drawRequest(message: self::DEAD_LINK, status: 404);
        }

        return $this->draw(PasswordResetView::SET, $token);
    }

    /** The new password, behind a link that is spent in the act of using it. */
    private function set(string $token, Request $request): Response
    {
        if (!$this->csrf->isValid($this->token($request))) {
            return $this->draw(PasswordResetView::SET, $token, message: self::STALE_FORM, status: 403);
        }

        $password = $this->text($request->input(self::PASSWORD_FIELD));
        $confirm = $this->text($request->input(self::CONFIRM_FIELD));

        if (mb_strlen($password) < self::MINIMUM_LENGTH) {
            return $this->draw(PasswordResetView::SET, $token, message: \sprintf(
                'A password needs at least %d characters. Length is what makes one hard to guess; '
                . 'there are no other rules.',
                self::MINIMUM_LENGTH,
            ), status: 422);
        }

        if ($password !== $confirm) {
            return $this->draw(
                PasswordResetView::SET,
                $token,
                message: 'The two passwords are not the same. Type both again.',
                status: 422,
            );
        }

        // Claimed only once the password is acceptable: a link spent on a
        // mistyped confirmation is a link somebody has to ask for again.
        $email = $this->resets->claim($token);

        if ($email === null) {
            return $this->drawRequest(message: self::DEAD_LINK, status: 404);
        }

        if ($this->users->setPassword($email, $password) !== 1) {
            // The account went away between the mail and the form. Answered
            // like a dead link, because from here it is one.
            return $this->drawRequest(message: self::DEAD_LINK, status: 404);
        }

        // Anything else outstanding for this address is no longer expected,
        // and the login lockout goes with it: somebody who reset their
        // password precisely because they were locked out must not still be.
        $this->resets->invalidateAll($email);
        $this->throttle->clear(Throttle::LOGIN, $email);

        $this->flashes->add('success', 'Your password is set. Sign in with it.');

        // Post, redirect, get: refreshing the page afterwards must not repost
        // a password to a token that is now spent.
        return Response::redirect($this->urls->route('login.form'));
    }

    private function drawRequest(
        string $email = '',
        ?string $message = null,
        ?string $notice = null,
        int $status = 200,
    ): Response {
        return $this->draw(PasswordResetView::REQUEST, '', $email, $message, $notice, $status);
    }

    private function draw(
        string $mode,
        string $token,
        string $email = '',
        ?string $message = null,
        ?string $notice = null,
        int $status = 200,
    ): Response {
        $view = new PasswordResetView(
            shell: new ShellView($this->brand, styles: $this->assets->styles(), darkMode: $this->darkMode),
            action: $mode === PasswordResetView::SET
                ? $this->urls->route('password.reset.submit', ['token' => $token])
                : $this->urls->route('password.request.submit'),
            token: $this->csrf->token(),
            mode: $mode,
            title: $mode === PasswordResetView::SET ? 'Set a new password' : 'Forgotten password',
            loginUrl: $this->urls->route('login.form'),
            email: $email,
            message: $message,
            notice: $notice,
        );

        return Response::html($this->renderer->render('page/password', $view), $status);
    }

    /** From the body for an ordinary post, from the header for one core.js sent. */
    private function token(Request $request): ?string
    {
        $body = $request->input(Csrf::FIELD);

        return \is_string($body) ? $body : $request->header(Csrf::HEADER);
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
