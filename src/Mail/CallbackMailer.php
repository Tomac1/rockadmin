<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

use Closure;
use Throwable;

/**
 * The project's own mailer, handed over as a closure.
 *
 * Three lines, and the driver most real projects should use. A host running
 * Laravel, Symfony or anything else has a mailer that is already configured,
 * already queued, already retried and already warmed up with whoever delivers
 * its mail; routing a password reset through it is a better message than
 * anything this SDK can send on its own.
 *
 * **The closure comes from the bootstrap, not from configuration** --
 * specification 11.2's rule, and the reason is mechanical rather than
 * stylistic: a closure cannot be written into `config.cache.php`, so a
 * configuration holding one could never be cached, and the cache is what makes
 * production a single `include`.
 *
 * A failure inside the closure is wrapped rather than swallowed, so a caller
 * catches one exception type whichever driver is wired. The original is kept
 * as the previous exception, because the host's own message is the one that
 * says what actually went wrong.
 */
final class CallbackMailer implements Mailer
{
    /** @param Closure(Message): void $send */
    public function __construct(private readonly Closure $send)
    {
    }

    public function send(Message $message): void
    {
        try {
            ($this->send)($message);
        } catch (MailException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new MailException(
                "The project's own mailer did not send '{$message->kind}': " . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
