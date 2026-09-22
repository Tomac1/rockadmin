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
 */
final class Escaper
{
    private const FLAGS = ENT_QUOTES | ENT_SUBSTITUTE;

    /**
     * A URL scheme that executes when a browser follows it. The comparison
     * strips whitespace first, because a browser ignores whitespace inside a
     * scheme and "java\tscript:" is a working link.
     */
    private const EXECUTABLE_SCHEMES = ['javascript', 'data', 'vbscript'];

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

    /** Escapes a URL, refusing schemes that run code instead of navigating. */
    public function url(mixed $value): string
    {
        $url = $this->stringify($value);
        $scheme = $this->scheme($url);

        if ($scheme !== null && \in_array($scheme, self::EXECUTABLE_SCHEMES, true)) {
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
     * @param array<string, scalar|null> $attributes
     */
    public function attributes(array $attributes): string
    {
        $out = '';

        foreach ($attributes as $name => $value) {
            if (preg_match('/^[A-Za-z_:][A-Za-z0-9_:.-]*$/', $name) !== 1) {
                throw new ViewException("Refusing '{$name}' as an attribute name.");
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

    private function scheme(string $url): ?string
    {
        $candidate = preg_replace('/\s+/', '', $url) ?? $url;
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
}
