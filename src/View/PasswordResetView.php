<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * The two password pages, prepared for their template.
 *
 * `LoginView`'s sibling, and drawn by the same kind of template: a whole
 * document with no shell navigation, because the person reading it is nobody
 * yet.
 *
 * **One view and one template for both steps**, told apart by `$mode`. They
 * are the same page with a different field in the middle -- the same brand,
 * the same alert, the same single button -- and two templates would be two
 * places to keep that agreement, which is how the second one ends up looking
 * subtly unlike the first.
 *
 * **There is no password property here either.** For the reason `LoginView`
 * gives: writing a typed password back into the response body puts a secret
 * into view-source and into whatever proxy or error reporter keeps one. A
 * refused new password is retyped, both halves of it.
 */
final class PasswordResetView
{
    /** The form that asks for an address. */
    public const string REQUEST = 'request';

    /** The form that takes a new password, behind a live link. */
    public const string SET = 'set';

    public function __construct(
        public readonly ShellView $shell,
        /** Where the form posts, already built. */
        public readonly string $action,
        public readonly string $token,
        /** `request` or `set`. */
        public readonly string $mode,
        public readonly string $title,
        /** Back to the login page, for somebody who remembered after all. */
        public readonly string $loginUrl,
        /** Redrawn after a refusal; never a password. */
        public readonly string $email = '',
        /** Why the form came back. */
        public readonly ?string $message = null,
        /**
         * What happened, when nothing went wrong.
         *
         * Separate from `$message` because they are drawn differently and
         * mean opposite things, and because the constant answer to a reset
         * request -- "if that address belongs to an administrator" -- is a
         * notice rather than an error however the request went.
         */
        public readonly ?string $notice = null,
        public readonly string $hint = '',
    ) {
    }

    public function isRequest(): bool
    {
        return $this->mode === self::REQUEST;
    }

    public function classes(): string
    {
        return Classes::of('login-form') . ' ' . Classes::identity('password-form', $this->mode);
    }

    public function bodyClasses(): string
    {
        return Classes::of('login') . ' ' . Classes::identity('page-type', 'password');
    }
}
