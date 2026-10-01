<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * What an attempt answered: an outcome, the user on success, and the sentence
 * the person is shown.
 *
 * **The message never says which check failed.** One sentence for a wrong
 * password, an address nobody has registered and an account that has been
 * deactivated — because the differences between them are exactly what an
 * attacker is trying to learn, and an admin with five users has no support
 * cost to weigh against that. One sentence for both throttle limits, for the
 * same reason: telling somebody it was *their address* that ran out tells them
 * to change it.
 *
 * **The outcome does distinguish them, for the caller.** The handler flashes a
 * refusal and logs a throttle, so it must be able to tell them apart without
 * matching on a sentence that a host may translate.
 *
 * The messages are constants rather than configuration. They are not a
 * configurable policy — they are the two things this layer is allowed to say —
 * and a host that wants them in another language replaces them at the point
 * where it turns a result into a page, where every other sentence it shows is
 * already translated.
 */
final class LoginResult
{
    /**
     * Deliberately vague about *which* of the three refusals this is, and
     * deliberately specific that nothing was granted.
     */
    public const REFUSED = 'Those details do not match an account that can sign in.';

    /**
     * Deliberately silent about which limit was reached, and about how long
     * the wait is: a precise countdown is a measuring instrument for whoever
     * is filling the counter.
     */
    public const THROTTLED = 'Too many sign-in attempts. Please wait a few minutes and try again.';

    private function __construct(
        public readonly LoginOutcome $outcome,
        public readonly ?AuthUser $user,
        public readonly string $message,
    ) {
    }

    public static function success(AuthUser $user): self
    {
        return new self(LoginOutcome::Success, $user, '');
    }

    /** Wrong password, unknown email, or an account that may not sign in. */
    public static function refused(): self
    {
        return new self(LoginOutcome::Refused, null, self::REFUSED);
    }

    public static function throttled(): self
    {
        return new self(LoginOutcome::Throttled, null, self::THROTTLED);
    }

    public function succeeded(): bool
    {
        return $this->outcome === LoginOutcome::Success;
    }

    /**
     * Named `wasThrottled()` rather than `throttled()` because the static
     * constructor already owns that name, and a class with both reads as
     * though one of them is the other's opposite.
     */
    public function wasThrottled(): bool
    {
        return $this->outcome === LoginOutcome::Throttled;
    }
}
