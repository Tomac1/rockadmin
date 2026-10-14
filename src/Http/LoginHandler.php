<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Auth\Authenticator;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\LoginResult;
use RockAdmin\Form\FormFields;
use RockAdmin\View\Assets;
use RockAdmin\View\FlashBag;
use RockAdmin\View\LoginView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;

/**
 * `GET login` draws the form; `POST login` attempts it.
 *
 * One handler for two routes, for the reason `FormHandler` serves three: the
 * document, the view object and the return address are identical, and the only
 * thing the route name decides is whether an attempt is made first. A refused
 * attempt is redrawn by the same method that drew the fresh form, so "the form
 * again, with a message" is literally the same form rather than a second
 * rendering that resembles it.
 *
 * **Both routes are `RouteAccess::Anonymous`,** which means the gate does not
 * run in front of them and whatever protects them is this class's own job.
 * What protects the POST is the CSRF token and `Authenticator`'s throttle;
 * what protects the GET is that it grants nothing.
 *
 * **Three statuses, and each says something a client can act on.** 200 for a
 * refusal, because the person is looking at the form they must fill in again;
 * 429 for a throttled attempt, because that is a fact about the request rather
 * than about the credentials, and it is the one difference between the two
 * refusals — `LoginResult` keeps them apart for the caller precisely so this
 * class can, while the sentence shown stays the same either way and names
 * neither limit; 403 for a request that carried no valid token, redrawn rather
 * than thrown, because the login page is the one page in the admin a person
 * reaches by a stale bookmark and a bare error page there is a dead end.
 *
 * **The client's address is a constructor argument, not something read from
 * the request.** `Request` deliberately carries no notion of a remote address,
 * and `Authenticator::attempt()` says the caller decides whether it trusts a
 * proxy header for this. That decision belongs to the host — it is the only
 * thing that knows whether it sits behind a load balancer it controls — so the
 * host states the address once, here, and this class passes it on.
 */
final class LoginHandler implements Handler
{
    /**
     * The two body keys the form carries that are not `Csrf::FIELD` or
     * `FormFields::RETURN_TO`.
     *
     * Constants for the same reason `FormFields` exists: the template writes
     * them and this class reads them back, and the two never call each other.
     */
    public const string EMAIL_FIELD = 'email';

    public const string PASSWORD_FIELD = 'password';

    /**
     * What a throttled person is told to do, since there is nothing they can
     * do.
     *
     * Password reset needs `Mailer` and arrives in a later milestone, so an
     * administrator who has locked themselves out has no self-service way
     * back in. Saying nothing would leave them retyping a password they know
     * is right and watching it be refused, which reads as a broken
     * installation; telling them the limit clears by itself is true, is the
     * only action available, and still names neither of the two limits.
     */
    public const string THROTTLE_HINT = 'This limit clears by itself after a few minutes. '
        . 'Until it does, a correct password will be refused as well.';

    /** Shown when the token is missing or stale rather than wrong-looking. */
    public const string STALE_FORM = 'This form has expired. Please try again.';

    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly Identity $identity,
        private readonly Csrf $csrf,
        private readonly Renderer $renderer,
        private readonly Assets $assets,
        private readonly UrlGenerator $urls,
        private readonly string $brand,
        private readonly string $clientIp,
        private readonly string $darkMode = 'auto',
        /**
         * Flashes left by whatever sent somebody here.
         *
         * Only one thing does today: a password reset redirects to this page
         * after setting a password, and "your password is set, sign in with
         * it" belongs on the form rather than on a page of its own. This page
         * draws no toasts -- it loads no script, and `.toast` is hidden until
         * one shows it -- so the message is drawn as an alert in the flow of
         * the form instead.
         *
         * Optional, because a great many tests build this handler to check one
         * sign-in and have no session to flash into.
         */
        private readonly ?FlashBag $flashes = null,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        // Somebody already signed in has no business on this page, and drawing
        // it for them is how a person with a stale tab signs in a second time
        // and loses whichever session the other tab was using.
        if ($this->identity->isAuthenticated()) {
            return $this->onwards();
        }

        if ($route->name === 'login.submit') {
            return $this->attempt($request);
        }

        return $this->draw($this->returnPath($this->identity->intended()));
    }

    /**
     * The attempt, and the three things it can answer.
     *
     * The token is checked before the email is so much as read: a request that
     * cannot prove where it came from does not get to fill a throttle counter
     * for an address somebody else owns.
     */
    private function attempt(Request $request): Response
    {
        $email = $this->text($request->input(self::EMAIL_FIELD));
        $returnPath = $this->returnPath(
            $request->input(FormFields::RETURN_TO) ?? $this->identity->intended(),
        );

        if (!$this->csrf->isValid($this->token($request))) {
            return $this->draw($returnPath, $email, self::STALE_FORM, status: 403);
        }

        $result = $this->authenticator->attempt($email, $this->text($request->input(self::PASSWORD_FIELD)), $this->clientIp);

        if ($result->wasThrottled()) {
            return $this->draw($returnPath, $email, $result->message, self::THROTTLE_HINT, 429);
        }

        if (!$result->succeeded() || $result->user === null) {
            return $this->draw($returnPath, $email, $result->message);
        }

        // `Identity::login()` reissues the session id before it writes
        // anything into it. Without that, a token an attacker fixed in the
        // browser before the login is still valid after it, and the privilege
        // this line just granted is granted to that token too.
        $this->identity->login($result->user);

        return $this->onwards($returnPath);
    }

    /**
     * Where somebody goes once they are signed in: back where they were
     * headed, or the dashboard.
     *
     * `FormHandler::returnTo()` is the one spelling of "turn a `_ret` into a
     * URL" in this project, and it is `ReturnAddress` underneath — which
     * refuses anything that is not an internal path and clamps anything
     * outside the mount point to the admin's own root. So every one of
     * absent, malformed, foreign and outside-the-admin lands somewhere valid,
     * and no second validator is written here.
     */
    private function onwards(string $returnPath = ''): Response
    {
        $this->identity->forgetIntended();

        $url = FormHandler::returnTo($returnPath, $this->urls) ?? $this->urls->route('dashboard');

        return Response::redirect($url);
    }

    /**
     * The form, fresh or redrawn.
     *
     * The email is carried back and the password is not. That is a small
     * disclosure — the address sits in a response body and in the browser's
     * history — weighed against a real cost: an administrator who mistypes
     * their password and loses their address with it retypes both, and every
     * retype spends one of the attempts the throttle is counting. The address
     * is also not a secret from the person who just typed it, and every
     * browser's own form restoration puts it back anyway.
     */
    private function draw(
        string $returnPath,
        string $email = '',
        ?string $message = null,
        string $hint = '',
        int $status = 200,
    ): Response {
        $shell = new ShellView(
            $this->brand,
            styles: $this->assets->styles(),
            darkMode: $this->darkMode,
        );

        $view = new LoginView(
            $shell,
            $this->urls->route('login.submit'),
            $this->csrf->token(),
            email: $email,
            returnTo: $returnPath,
            message: $message,
            hint: $hint,
            notice: $this->notice(),
        );

        return Response::html($this->renderer->render('page/login', $view), $status);
    }

    /**
     * The first thing flashed to this session, taken rather than read.
     *
     * Taken, because a flash is shown once: a message still sitting there
     * after the page that shows it would reappear on the next refused
     * attempt, which is how "your password is set" ends up under "that was
     * not the right password".
     *
     * Only the first. This page has room for one sentence, and the only
     * caller flashes one.
     */
    private function notice(): ?string
    {
        foreach ($this->flashes?->take() ?? [] as $flash) {
            return $flash->message;
        }

        return null;
    }

    /**
     * The path a hidden field may carry, or ''.
     *
     * `ReturnAddress::path()` rather than `url()`: what goes into the form is
     * the address as it arrived, and the clamping to the admin's own root
     * happens when it is turned into a `Location` header. Doing it here as
     * well would mean a person bounced from outside the mount point sat in
     * front of a form whose hidden field said "the dashboard", which is a
     * different fact from the one they arrived with.
     */
    private function returnPath(mixed $raw): string
    {
        return ReturnAddress::from($raw)?->path() ?? '';
    }

    /** From the body for an ordinary post, from the header for one core.js sent. */
    private function token(Request $request): ?string
    {
        $body = $request->input(Csrf::FIELD);

        return \is_string($body) ? $body : $request->header(Csrf::HEADER);
    }

    /** A body value that is not a string is not something somebody typed. */
    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
