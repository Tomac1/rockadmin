<?php

declare(strict_types=1);

namespace RockAdmin\View;

use Stringable;

/**
 * Escaping, as the templates call it.
 *
 * Everything here is deliberately small and total: given any value a view
 * object might hold, each method either returns something safe to put in HTML
 * or throws. There is no "best effort" path, because a best effort at
 * escaping is a cross-site scripting hole that looks like it works.
 *
 * These methods are safe for HTML text content and for quoted attribute
 * values. They are not safe for use in a <script> or <style> body, or in an
 * intrinsic event handler like onclick — those contexts have different parsing
 * rules and require different escaping. This project does not put values into
 * those contexts; behaviour is declared with data-ra-* attributes and bound
 * by delegation.
 */
final class Escaper
{
    private const FLAGS = ENT_QUOTES | ENT_SUBSTITUTE;

    /**
     * URL schemes this project allows. An allow-list is safer than a deny-list
     * because new dangerous schemes ship regularly (blob, filesystem), but this
     * project only needs a few schemes: browsing paths, navigating to external
     * sites, and emailing or calling. A URL with no scheme is ordinary: /path.
     * A scheme-relative URL like //evil.com is a redirect vector and refused.
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Escapes for element content and for quoted attribute values alike. */
    public function text(mixed $value): string
    {
        return htmlspecialchars($this->stringify($value), self::FLAGS, 'UTF-8');
    }

    /**
     * The same escaping as text(), named for where it is used. Templates read
     * better for it, and a later change to attribute handling has one place
     * to land.
     */
    public function attr(mixed $value): string
    {
        return $this->text($value);
    }

    /** Escapes a URL, allowing only safe schemes or relative paths. */
    public function url(mixed $value): string
    {
        $url = $this->stringify($value);

        if ($this->isSchemeRelative($url)) {
            throw new ViewException("Refusing a scheme-relative URL: '{$url}' leads off this site.");
        }

        $scheme = $this->scheme($url);

        if ($scheme !== null && !\in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new ViewException("Refusing to write a '{$scheme}:' URL into a link.");
        }

        return $this->text($url);
    }

    /**
     * Returns its input unchanged. It exists so that unescaped output is
     * written deliberately, shows up in a diff, and can be found by grep — the
     * CI template check fails on any `<?=` that calls neither this nor text().
     */
    public function raw(mixed $value): string
    {
        return $this->stringify($value);
    }

    /**
     * Builds an attribute list with a leading space, so a template writes
     * `<tr<?= $attrs($view->attributes) ?>>` without worrying about spacing.
     *
     * true writes a bare attribute, null and false omit it entirely, and an
     * empty string writes `attr=""`.
     *
     * @param array<int|string, scalar|null> $attributes
     */
    public function attributes(array $attributes): string
    {
        $out = '';

        foreach ($attributes as $name => $value) {
            // Cast to string to handle numeric keys that PHP coerces to int.
            $name = (string) $name;

            if (preg_match('/^[A-Za-z_:][A-Za-z0-9_:.-]*$/', $name) !== 1) {
                throw new ViewException("Refusing '{$name}' as an attribute name.");
            }

            // Refuse event handlers. They are unsafe even with escaped values
            // because browsers entity-decode an event handler value before
            // executing it as JavaScript. Use data-ra-* attributes instead.
            if (preg_match('/^on.+$/i', $name) === 1) {
                throw new ViewException(
                    "Refusing '{$name}' as an attribute name. "
                    . 'Use data-ra-* attributes instead; behaviour is declared with them and bound by delegation.',
                );
            }

            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $out .= ' ' . $name;
                continue;
            }

            $out .= ' ' . $name . '="' . $this->text($value) . '"';
        }

        return $out;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            \is_string($value) => $value,
            $value === null, $value === false => '',
            $value === true => '1',
            \is_int($value), \is_float($value) => (string) $value,
            $value instanceof Stringable => (string) $value,
            \is_array($value) => throw new ViewException(
                'Cannot escape an array. A view object handed a template an array where a value belongs.',
            ),
            default => throw new ViewException(
                'Cannot escape a value of type ' . get_debug_type($value) . '.',
            ),
        };
    }

    /**
     * Whether the URL leads off this site without naming a scheme. //evil.com
     * is an open redirect, and nothing in this admin needs one. It is asked
     * separately from scheme() because it has no scheme to return.
     */
    private function isSchemeRelative(string $url): bool
    {
        return str_starts_with($this->strip($url), '//');
    }

    private function scheme(string $url): ?string
    {
        $candidate = $this->strip($url);

        $colon = strpos($candidate, ':');

        if ($colon === false) {
            return null;
        }

        $scheme = substr($candidate, 0, $colon);

        // A path segment may contain a colon ("/p/ads/a:b"), which is not a
        // scheme. A scheme cannot contain a slash or a question mark.
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*$/', $scheme) !== 1) {
            return null;
        }

        return strtolower($scheme);
    }

    /**
     * The URL as a browser reads it when deciding what it is. C0 controls and
     * spaces are removed from everywhere, not only from the ends: a browser
     * ignores them inside a scheme too, so "java\tscript:" is a working link
     * and a leading control byte hides a scheme from a naive parser without
     * hiding it from the browser.
     */
    private function strip(string $url): string
    {
        return preg_replace('/[\x00-\x20]/', '', $url) ?? $url;
    }
}
