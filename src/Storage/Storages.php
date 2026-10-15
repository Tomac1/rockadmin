<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

use RockAdmin\Config\Config;
use RockAdmin\Http\HttpClient;
use RockAdmin\Http\HttpClients;
use RockAdmin\Http\UrlGenerator;

/**
 * Which storage the configuration asked for.
 *
 * `Mailers`' sibling, and for the same reason: one place reads
 * `storage.driver`, so moving an installation from a directory to a bucket is
 * a line in `rockadmin.php` and nothing else -- and so that a misconfiguration
 * is refused here, by name, rather than becoming a 403 from somebody else's
 * server at the moment a person presses Save.
 */
final class Storages
{
    public static function fromConfig(
        Config $config,
        ?UrlGenerator $urls = null,
        ?HttpClient $http = null,
    ): Storage {
        $driver = self::text($config->get('storage.driver', 'local'));

        return match ($driver) {
            'local' => self::local($config, $urls),
            's3' => self::s3($config, $http),
            default => throw new StorageException(\sprintf(
                "storage.driver is '%s', which is not a driver this admin has. It is 'local' (a "
                . "directory) or 's3' (R2, MinIO, B2, Spaces, Amazon -- anything speaking that API). "
                . 'A project with its own disk abstraction passes a Storage to the bootstrap instead '
                . 'and sets neither.',
                $driver,
            )),
        };
    }

    private static function local(Config $config, ?UrlGenerator $urls): LocalStorage
    {
        $path = self::text($config->get('storage.path', ''));

        if ($path === '') {
            throw new StorageException(
                "storage.driver is 'local' and storage.path is empty, so there is nowhere to put a "
                . 'file. It is a directory outside the document root -- a directory of uploads '
                . 'inside it is one a stranger can fetch by guessing.',
            );
        }

        return new LocalStorage($path, $urls, self::text($config->get('storage.public_url', '')));
    }

    private static function s3(Config $config, ?HttpClient $http): S3Storage
    {
        foreach (['endpoint', 'bucket', 'key', 'secret'] as $required) {
            if (self::text($config->get("storage.{$required}")) === '') {
                throw new StorageException(\sprintf(
                    "storage.driver is 's3' and storage.%s is empty. R2 needs the account endpoint, "
                    . 'the bucket, an access key id and its secret; all four come from the token you '
                    . 'created, and the endpoint is not the public bucket address.',
                    $required,
                ));
            }
        }

        $minutes = $config->get('storage.link_minutes', 10);

        return new S3Storage(
            // The caller's client wins, because a host with its own -- a proxy
            // to go through, its own timeouts, its own retries -- should use
            // it; otherwise whichever this installation can actually run.
            $http ?? HttpClients::best(),
            new SignatureV4(
                self::text($config->get('storage.key')),
                self::text($config->get('storage.secret')),
                self::text($config->get('storage.region', 'auto')),
            ),
            self::text($config->get('storage.endpoint')),
            self::text($config->get('storage.bucket')),
            (\is_int($minutes) ? $minutes : 10) * 60,
            self::text($config->get('storage.public_url', '')),
        );
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
