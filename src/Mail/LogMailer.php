<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

use DateTimeImmutable;
use Throwable;

/**
 * The default driver: one `.eml` file per message, and nothing sent.
 *
 * `logs/mail/2026-09-21_143012_password-reset_jan@example.com.eml`, complete
 * with headers, which a mail client opens as the message it would have been.
 * That is the whole design: a developer who has just built a password reset
 * wants to read the link and click it, and a driver that printed "mail sent"
 * to a log would make them go and find the token in the database.
 *
 * **It is the default because the alternative defaults are worse.** `sendmail`
 * on a developer machine either fails or -- occasionally, catastrophically --
 * works, and an SMTP default would be a default pointing at nothing. Writing a
 * file always works and never surprises anybody.
 *
 * **The directory is under `logs`, which configuration already requires to be
 * outside the document root.** A sent message contains a working reset link,
 * so a web-readable mail log is a way in.
 */
final class LogMailer implements Mailer
{
    public function __construct(
        /** The `logs` path from configuration; `mail/` is created inside it. */
        private readonly string $logPath,
    ) {
    }

    public function send(Message $message): void
    {
        $directory = rtrim(str_replace('\\', '/', $this->logPath), '/') . '/mail';

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new MailException(\sprintf(
                "The mail log directory '%s' does not exist and could not be created. The 'logs' "
                . 'path in rockadmin.php has to be writable, and outside the document root.',
                $directory,
            ));
        }

        $at = new DateTimeImmutable();
        $file = \sprintf(
            '%s/%s_%s_%s.eml',
            $directory,
            $at->format('Y-m-d_His'),
            self::slug($message->kind),
            self::slug($message->to->address),
        );

        try {
            $written = file_put_contents($file, (new Mime($message, $at))->full(), LOCK_EX);
        } catch (Throwable $e) {
            throw new MailException("The message could not be written to '{$file}': " . $e->getMessage(), previous: $e);
        }

        if ($written === false) {
            throw new MailException("The message could not be written to '{$file}'.");
        }
    }

    /**
     * A filename component, with everything that is not plainly safe replaced.
     *
     * The address is in the name because that is what makes the directory
     * usable -- "the one that went to Jana" -- and an address is also user
     * input that reaches a filesystem path. `..` and separators are gone by
     * construction: what survives is letters, digits, dot, dash, underscore
     * and `@`, and a run of anything else becomes one dash.
     */
    private static function slug(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._@-]+/', '-', $value) ?? '';
        $safe = trim(str_replace('..', '-', $safe), '-.');

        return $safe === '' ? 'message' : substr($safe, 0, 80);
    }
}
