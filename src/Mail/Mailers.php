<?php

declare(strict_types=1);

namespace RockAdmin\Mail;

use Closure;
use RockAdmin\Config\Config;

/**
 * Which driver the configuration asked for.
 *
 * One place reads `mail.driver`, so a host wires `Mailers::fromConfig(...)`
 * and gets whatever the file says -- including the day somebody changes `log`
 * to `smtp` in production and nothing else has to be edited.
 *
 * **The callback driver's closure is an argument, not a key.** Specification
 * 11.2: configuration holds data, the host supplies services. A closure cannot
 * be written into `config.cache.php`, so a configuration holding one could
 * never be cached -- and the cache is what makes production a single
 * `include`.
 */
final class Mailers
{
    /**
     * @param ?Closure(Message): void $callback the host's own mailer, for the `callback` driver
     */
    public static function fromConfig(Config $config, ?Closure $callback = null): Mailer
    {
        $driver = self::text($config->get('mail.driver', 'log'));

        return match ($driver) {
            'log' => new LogMailer(self::text($config->get('paths.logs', ''))),
            'sendmail' => new SendmailMailer(),
            'smtp' => self::smtp($config),
            'callback' => new CallbackMailer($callback ?? throw new MailException(
                "mail.driver is 'callback', which means the project sends the mail itself -- but no "
                . 'closure was passed to the bootstrap. Pass one, or choose a driver that does not '
                . 'need it.',
            )),
            default => throw new MailException(\sprintf(
                "mail.driver is '%s', which is not a driver this admin has. It is one of: log, smtp, "
                . 'sendmail, callback.',
                $driver,
            )),
        };
    }

    /** Who the mail is from, which every driver but `callback` needs. */
    public static function from(Config $config): Mailbox
    {
        $address = self::text($config->get('mail.from.address', ''));

        if ($address === '') {
            throw new MailException(
                'mail.from.address is empty, so there is nobody for a password reset to come from. '
                . 'Set it to an address at a domain this server may send for -- anything else is '
                . 'filed as spam whatever the driver does.',
            );
        }

        return new Mailbox($address, self::text($config->get('mail.from.name', '')));
    }

    private static function smtp(Config $config): SmtpMailer
    {
        $host = self::text($config->get('mail.host', ''));

        if ($host === '') {
            throw new MailException(
                "mail.driver is 'smtp' and mail.host is empty. There is nothing to connect to.",
            );
        }

        $port = $config->get('mail.port', 587);
        $timeout = $config->get('mail.timeout', 10);
        $encryption = self::text($config->get('mail.encryption', 'tls'));

        if (!\in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new MailException(\sprintf(
                "mail.encryption is '%s'. It is 'tls' (STARTTLS, usually port 587), 'ssl' (an "
                . "encrypted socket, usually port 465) or 'none'.",
                $encryption,
            ));
        }

        $port = \is_int($port) ? $port : 587;
        $timeout = \is_int($timeout) ? $timeout : 10;

        return new SmtpMailer(
            // A fresh connection per message. One held between requests is one
            // that has timed out by the next.
            transport: static fn (): SmtpTransport => new StreamTransport($host, $port, $timeout, $encryption),
            username: self::optional($config->get('mail.username')),
            password: self::optional($config->get('mail.password')),
            encryption: $encryption,
            clientName: gethostname() ?: 'localhost',
        );
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private static function optional(mixed $value): ?string
    {
        return \is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
