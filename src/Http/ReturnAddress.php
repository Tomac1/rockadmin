<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * Where to send somebody once a write succeeds — `_ret`, validated.
 *
 * This is the open-redirect surface of the form machinery. A person follows
 * an edit link from a filtered, sorted, paged grid; the form carries the
 * address of that grid in its body so saving lands them back on it (spec
 * 8.11). Anybody can edit that body, so the value arriving back is a URL an
 * attacker chose, and `Response::redirect()` writes it into a `Location`
 * header verbatim.
 *
 * So the rule is a whitelist, not a blacklist: a value is either an absolute
 * path made of ordinary printable characters, with no scheme, no host, no
 * dot segments and nothing that could end a header line, or it is refused.
 * A refused value yields `null`, never `''`: `FormRegion` falls back to the
 * page's own index on `null` alone, and an empty string would travel into
 * the document as a real return address.
 *
 * The two halves of the check live in different methods because they need
 * different knowledge. `from()` is context-free — it can tell a path from a
 * URL without knowing anything about this installation. Only a
 * `UrlGenerator` knows where the admin is mounted, so "is this path inside
 * the admin at all" is `url()`'s question, asked at the moment the answer is
 * needed, and answered with the admin's own root rather than with whatever
 * was sent.
 *
 * Every check below is written as an explicit character test rather than as a
 * PCRE character class. A backslash inside a single-quoted PHP string inside
 * a character class compiles to a class matching nothing at all, and a
 * pattern that silently matches nothing is exactly the failure this class
 * exists to prevent.
 */
final class ReturnAddress
{
    /**
     * Longer than any address this admin builds, and short enough that a
     * `Location` header stays inside what every server and proxy accepts.
     * A value past this is not a return address somebody arrived with.
     */
    private const int MAX_LENGTH = 2048;

    private function __construct(private readonly string $path)
    {
    }

    /** Null when the value is absent or not an internal admin path. */
    public static function from(mixed $raw): ?self
    {
        if (!\is_string($raw) || $raw === '' || \strlen($raw) > self::MAX_LENGTH) {
            return null;
        }

        // Absolute, and rooted at the host: a relative path resolves against
        // whichever document happens to be showing, and "//host" is a host.
        if ($raw[0] !== '/' || str_starts_with($raw, '//')) {
            return null;
        }

        if (self::hasUnsafeCharacter($raw)) {
            return null;
        }

        if (self::hasEncodedControlCharacter($raw)) {
            return null;
        }

        if (self::hasScheme($raw)) {
            return null;
        }

        if (self::hasDotSegment($raw)) {
            return null;
        }

        return new self($raw);
    }

    /** What was accepted, exactly as it arrived. */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * The URL to redirect to: this path when it is inside the admin, and the
     * admin's own root when it is not.
     *
     * `$urls->to('')` is the admin's root URL in whichever mode this
     * installation runs — '/admin/' in path mode, '/admin/index.php' in
     * query mode — which is both the prefix to test against and the honest
     * fallback. Asking the generator for it rather than reading a base off
     * it keeps the one piece of knowledge about the mount point in the one
     * class that has it.
     */
    public function url(UrlGenerator $urls): string
    {
        $root = $urls->to('');

        if (str_starts_with($this->path, $root) || $this->path . '/' === $root) {
            return $this->path;
        }

        return $root;
    }

    /**
     * A control character, a space, a byte outside printable ASCII, or a
     * backslash.
     *
     * The control characters are the header-injection ones — a newline or a
     * carriage return ends a `Location` line — but a tab, a vertical tab and
     * a NUL are refused on the same rule rather than enumerated, because
     * none of them appears in a URL this admin builds and each one is
     * treated differently by a different proxy.
     *
     * A backslash is not a path separator to `Request::normalizePath()`, but
     * every browser normalises it to one before sending, so '/\evil.com'
     * requests '//evil.com' — a host. Refusing it here is the only place
     * that difference is visible.
     */
    private static function hasUnsafeCharacter(string $value): bool
    {
        $length = \strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $code = \ord($value[$i]);

            if ($code < 0x21 || $code > 0x7E) {
                return true;
            }

            if ($value[$i] === '\\') {
                return true;
            }
        }

        return false;
    }

    /**
     * A percent-escape that decodes to a control character.
     *
     * A literal '%0d%0a' in a `Location` header is not a header break by
     * itself — nothing on the way out decodes it. But something on the way
     * in might have encoded it, and a proxy or a framework in front of this
     * admin may decode it again before the browser sees it. The value is
     * refused rather than reasoned about: no address this admin builds
     * contains an encoded control character, so nothing legitimate is lost.
     */
    private static function hasEncodedControlCharacter(string $value): bool
    {
        $length = \strlen($value);

        for ($i = 0; $i + 2 < $length; $i++) {
            if ($value[$i] !== '%') {
                continue;
            }

            $escape = $value[$i + 1] . $value[$i + 2];

            if (!ctype_xdigit($escape)) {
                continue;
            }

            $decoded = (int) hexdec($escape);

            if ($decoded < 0x20 || $decoded === 0x7F) {
                return true;
            }
        }

        return false;
    }

    /**
     * A scheme, anywhere before the first slash.
     *
     * A value starting with '/' cannot carry one, so this is belt and
     * braces — but it is the check that would still hold if the leading
     * slash rule above were ever relaxed, and 'javascript:alert(1)' is
     * precisely the value that makes relaxing it expensive.
     */
    private static function hasScheme(string $value): bool
    {
        $slash = strpos($value, '/');
        $head = $slash === false ? $value : substr($value, 0, $slash);

        return str_contains($head, ':');
    }

    /**
     * A '.' or '..' segment in the path portion.
     *
     * A browser resolves these before it makes the request, so
     * '/admin/../etc/passwd' fetches '/etc/passwd' — which means `url()`'s
     * "inside the admin" test would be answering a question about an address
     * nobody is going to visit. The query string is left alone: a '..' in a
     * parameter value is data, not a path.
     */
    private static function hasDotSegment(string $value): bool
    {
        $question = strpos($value, '?');
        $path = $question === false ? $value : substr($value, 0, $question);

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }
}
