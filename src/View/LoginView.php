<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * The login page, prepared for its template.
 *
 * It carries a `ShellView` for the same reason every other page does — the
 * brand, the stylesheets and the theme are the shell's job and there is no
 * second opinion about them — but the login template draws none of the shell's
 * navigation. A menu is a list of pages somebody may open, and the person
 * reading this page is nobody yet.
 *
 * **There is no password property, and there must never be one.** Every other
 * field of a refused form comes back holding what was typed, because a form
 * that lost it is a form somebody fills in twice. A password is the exception:
 * writing it back puts a secret into the response body, into view-source, and
 * into whatever proxy or error reporter keeps one. `region/form/field/password`
 * makes the same decision for the ordinary form machinery, in a template; here
 * it is made in the type, so no template can undo it.
 *
 * `message` and `hint` are two sentences and not one. `message` is whatever
 * `LoginResult` said, which never names which check failed; `hint` is the
 * handler's own advice about what to do next, which for a throttled attempt is
 * the only thing standing between an administrator and a support ticket,
 * because there is no password reset in this version.
 */
final class LoginView
{
    public function __construct(
        public readonly ShellView $shell,
        /** Where the form posts: the `login.submit` route, already built. */
        public readonly string $action,
        public readonly string $token,
        public readonly string $title = 'Sign in',
        /** Redrawn after a refusal; never the password. */
        public readonly string $email = '',
        /** The validated path to come back to, or '' for none. */
        public readonly string $returnTo = '',
        public readonly ?string $message = null,
        public readonly string $hint = '',
        /**
         * Something that went right, said on the page somebody lands on
         * afterwards.
         *
         * Separate from `message` because they mean opposite things and are
         * drawn differently. It exists because a password that has just been
         * set is news the person needs on the login form -- the alternative
         * was for the reset to render its own "done" page, which is a page
         * whose only content is a sentence and a link back here.
         */
        public readonly ?string $notice = null,
    ) {
    }

    public function classes(): string
    {
        return Classes::of('login-form');
    }

    public function bodyClasses(): string
    {
        return Classes::of('login') . ' ' . Classes::identity('page-type', 'login');
    }
}
