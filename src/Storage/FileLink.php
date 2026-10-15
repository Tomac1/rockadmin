<?php

declare(strict_types=1);

namespace RockAdmin\Storage;

use RockAdmin\Http\SessionStore;

/**
 * The signature on a private file URL: this key, until this moment, for this
 * session.
 *
 * **Why a signature at all, when the route already requires signing in.**
 * `Gate` makes `_file` authenticated, which is the floor and not the answer:
 * it says a stranger cannot fetch anything, and says nothing about one
 * administrator fetching another's. A key is sixteen random bytes and is
 * therefore hard to guess -- but "hard to guess" is a secret, not a
 * permission, and a key stops being secret the moment it appears in a log, a
 * screenshot, a database export or a bug report. In a multi-tenant admin,
 * where two administrators share one installation and not one set of rows,
 * that is the difference between an awkward URL and somebody else's
 * photograph.
 *
 * So a local URL is signed the same way the S3 driver signs one, and for the
 * same reasons: it expires, and it only works for the person it was drawn
 * for. Per-page and per-row permissions on files are v1.1 -- specification 13
 * puts column-level permissions out of v1 -- and until then this is what
 * stands between one administrator's link and another's hands.
 *
 * **The secret is the session's own, and is never configured.** One random
 * value per session, kept where the CSRF token is kept, which gives three
 * properties for free: nothing to generate at install time, nothing to rotate,
 * and a link that dies when the session does. It also makes "the session it
 * was built for" structural rather than checked -- a URL signed in one session
 * cannot verify in another, because the other one is holding a different
 * secret, so there is no identity to compare and no comparison to get wrong.
 *
 * **What it does not do.** It does not say the file is yours; it says this
 * link was made for you. A signature forwarded inside its lifetime by the
 * person it was made for works, exactly as a presigned S3 URL does. The
 * expiry is what bounds that, and it is short.
 */
final class FileLink
{
    /** The query parameters a signed URL carries, short because a key is already long. */
    public const string EXPIRY = 'e';

    public const string SIGNATURE = 's';

    private const string SESSION_KEY = '_rockadmin_file_key';

    /**
     * Half the signature, which is 128 bits of it.
     *
     * A truncated HMAC is as strong as its shortest part, and 128 bits is far
     * past what anybody can search for a URL that expires in ten minutes. The
     * full 64 hex characters would double the length of every `src` in a grid
     * of forty rows for nothing.
     */
    private const int LENGTH = 32;

    public function __construct(
        private readonly SessionStore $session,
        /** How long a link is good for, in seconds; `storage.link_minutes`, as for S3. */
        private readonly int $lifetime = 600,
    ) {
    }

    /**
     * The query a URL for this key carries.
     *
     * @return array{e: int, s: string}
     */
    public function queryFor(string $key): array
    {
        $expires = time() + $this->lifetime;

        return [self::EXPIRY => $expires, self::SIGNATURE => $this->sign($key, (string) $expires)];
    }

    /**
     * Whether this request may have this file.
     *
     * Both values come off a query string, so both arrive as whatever
     * somebody typed: `mixed` rather than `string` is the honest parameter
     * type, and everything that is not a plain string is simply not a
     * signature.
     */
    public function isValid(string $key, mixed $expiry, mixed $signature): bool
    {
        if (!\is_string($expiry) && !\is_int($expiry)) {
            return false;
        }

        if (!\is_string($signature) || $signature === '') {
            return false;
        }

        $expiry = (string) $expiry;

        // A whole number of seconds and nothing else: `'600abc'` must not
        // become 600 the way a cast would make it, and a leading `+`, a space
        // or an exponent must not reach the comparison either.
        if (preg_match('/^[0-9]{1,20}$/', $expiry) !== 1) {
            return false;
        }

        if ((int) $expiry <= time()) {
            return false;
        }

        return hash_equals($this->sign($key, $expiry), $signature);
    }

    private function sign(string $key, string $expiry): string
    {
        // The two parts are joined by a newline, which neither a storage key
        // nor a decimal number can contain, so no pair of values can sign as
        // another pair.
        return substr(hash_hmac('sha256', $key . "\n" . $expiry, $this->secret()), 0, self::LENGTH);
    }

    private function secret(): string
    {
        $secret = $this->session->get(self::SESSION_KEY);

        if (!\is_string($secret) || $secret === '') {
            $secret = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $secret);
        }

        return $secret;
    }
}
