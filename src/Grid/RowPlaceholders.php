<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use InvalidArgumentException;
use RockAdmin\Config\ActionReference;
use RockAdmin\Config\PageReference;
use RuntimeException;

/**
 * Substitutes a row placeholder — `{id}`, `{user_id}` — from a column of the
 * row being rendered (specification 8.5).
 *
 * **This is not the other kind of placeholder.** `{{user.id}}` is resolved once,
 * when configuration loads, from the signed-in administrator; `{id}` is
 * resolved here, per row, from that row's own data. Writing the first where you
 * meant the second puts the administrator's id on every row, and looks correct
 * in testing because the developer is usually looking at their own records. The
 * two are parsed by different code at different times and that separation is
 * the safeguard, so this class does not understand `{{…}}` — it only refuses it
 * by name, which is what makes the mistake visible at the moment it matters.
 *
 * Three properties the specification and this layer's position require:
 *
 * **Only a column the row carries is accepted.** A key the row does not carry
 * is refused, loudly, rather than substituted empty: an action pointing at
 * `/p/ads//edit` is a 404 whose cause is invisible, and the person who wrote
 * `{user_id}` against a region that does not select `user_id` needs to hear it.
 *
 * **The value cannot change the shape of the path.** It is substituted into one
 * path segment and must stay inside it, so a value carrying `/`, or a value
 * that *is* a dot segment, is refused — exactly as `UrlGenerator::route()`
 * refuses them, for the same reason: a slash is a separator again the moment
 * the host decodes the path, and `Request::normalizePath()` drops every dot
 * segment before a route is matched. Every row link a grid draws today goes
 * through `route()`, so this is the established rule rather than a new one.
 *
 * **The value is substituted raw, and encoded exactly once downstream.** The
 * single encoding layer is `UrlGenerator::build()`, which is the only code that
 * knows the URL mode and therefore how many layers the host will strip again.
 * Percent-encoding here as well would produce `%252F` in path mode and a
 * different number of layers in query mode — the same link yielding two
 * different row keys depending on a configuration key. So the specification's
 * "URL-encoded on substitution" is satisfied as a property of the produced URL,
 * and this class guarantees the premise that makes one layer sufficient: the
 * value occupies one segment and carries no brace for the layer above to
 * mistake for a placeholder nobody substituted.
 *
 * It therefore returns a reference, never a URL. Handing back a string would be
 * handing back an unencoded value with no record of what still had to happen
 * to it.
 */
final class RowPlaceholders
{
    /**
     * Anything that could be a placeholder — deliberately the same pattern
     * `UrlGenerator::reference()` uses to refuse an unsubstituted one.
     *
     * It is wider than a well-formed name on purpose. `{Id}` is a mistake, not
     * text: left alone it would reach that refusal a layer later and be blamed
     * on the template with no idea which column was meant. The two must not
     * disagree about what "unsubstituted" means, so they match the same thing
     * and this class, running first, is the one that explains it.
     */
    private const string CANDIDATE = '/\{[^}]*}/';

    /** A column name: the configuration-key convention and nothing more (spec 6.7). */
    private const string NAME = '/^[a-z][a-z0-9_]*$/';

    /**
     * Substitutes every row placeholder a reference carries.
     *
     * An `ActionReference` carries none by construction — it is one verb — and
     * is returned as it came, so a caller drawing a mixed list of actions does
     * not have to ask which kind it is holding.
     *
     * @param array<string, mixed> $row the row being rendered, as the RowSource gave it
     *
     * @return ($reference is PageReference ? PageReference : ActionReference)
     */
    public static function reference(PageReference|ActionReference $reference, array $row): PageReference|ActionReference
    {
        if ($reference instanceof ActionReference || !str_contains($reference->suffix, '{')) {
            return $reference;
        }

        return new PageReference(
            $reference->page,
            self::substitute($reference->suffix, $row, (string) $reference),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @param string               $subject what is being built, for the refusals to name
     */
    private static function substitute(string $template, array $row, string $subject): string
    {
        // Checked before anything is substituted, and checked on the whole
        // template rather than on one match, because '{{' is the mistake
        // itself: whatever follows it, the string was written as a
        // configuration placeholder in a place that resolves per row.
        if (str_contains($template, '{{')) {
            throw new InvalidArgumentException(
                "Cannot build the URL for '{$subject}': '{{' opens a configuration placeholder, which is "
                . 'resolved once when configuration loads, from the signed-in administrator — the same value '
                . 'on every row. A row placeholder names a column of the row being rendered and is written '
                . "with single braces: '{user_id}'.",
            );
        }

        return preg_replace_callback(
            self::CANDIDATE,
            static function (array $match) use ($row, $subject): string {
                $token = $match[0];
                $name = substr($token, 1, -1);

                if (preg_match(self::NAME, $name) !== 1) {
                    throw new InvalidArgumentException(
                        "Cannot build the URL for '{$subject}': '{$token}' is not a row placeholder. A row "
                        . 'placeholder names one column of the row, in the lower-case snake_case every '
                        . "configuration key uses: '{id}', '{user_id}'.",
                    );
                }

                if (!\array_key_exists($name, $row)) {
                    $carries = $row === []
                        ? 'The row carries no columns at all.'
                        : 'The row carries: ' . implode(', ', array_keys($row)) . '.';

                    throw new InvalidArgumentException(
                        "Cannot build the URL for '{$subject}': the row placeholder '{$token}' names no "
                        . "column of this row. {$carries} A row placeholder is substituted from the row "
                        . 'being rendered, so the region has to select the column — substituting nothing '
                        . "would produce a link like '/p/ads//edit', a 404 with no visible cause.",
                    );
                }

                return self::value($row[$name], $name, $subject);
            },
            $template,
        ) ?? throw new RuntimeException("Failed to substitute the row placeholders of '{$subject}'.");
    }

    /**
     * The one value a row placeholder may stand for: something that can be a
     * path segment addressing this row, and nothing else.
     *
     * Every refusal here is a value that would otherwise produce a URL
     * addressing a *different* row, or no row — which is the one thing a row
     * placeholder must never do.
     */
    private static function value(mixed $value, string $name, string $subject): string
    {
        $prefix = "Cannot build the URL for '{$subject}': the row's '{$name}' ";

        // NULL is refused rather than substituted empty, and this is a decision
        // rather than a detail. An empty segment gives '/p/ads//edit' — the
        // invisible 404 this class exists to prevent — and there is no value
        // that honestly stands for "this row points at nothing". Whether an
        // action is offered for such a row is what an action's `when` decides,
        // one layer up, where it can leave the action out instead of drawing a
        // link to nowhere.
        if ($value === null) {
            throw new InvalidArgumentException(
                $prefix . 'is NULL, so there is no row for the link to address. Substituting nothing would '
                . "produce '/p/ads//edit'. Use an action's 'when' to decide whether the action is offered "
                . 'for a row whose value is missing.',
            );
        }

        // A boolean has no spelling a URL agrees on -- '1'/'0', 'true'/'false',
        // and PHP's own (string) cast turns false into the empty string, which
        // is the empty-segment 404 again. A flag is not a row key.
        if (\is_bool($value)) {
            throw new InvalidArgumentException(
                $prefix . 'is a boolean, which has no agreed spelling in a URL — and PHP writes false as the '
                . 'empty string, which is an empty path segment. A row placeholder addresses a row, so it '
                . 'needs a key, not a flag.',
            );
        }

        // The grid carries JSON columns and one-to-many collections, so an
        // array really does arrive here. There is no encoding of one that a
        // route could read back, and inventing one (JSON, a comma list) would
        // be a spelling nothing else in the admin understands.
        if (!\is_scalar($value)) {
            throw new InvalidArgumentException(
                $prefix . 'is a ' . get_debug_type($value) . ', which has no form a URL can carry back. A '
                . 'JSON column or a collection cannot address a row; name a column holding the key instead.',
            );
        }

        $text = (string) $value;

        if ($text === '') {
            throw new InvalidArgumentException(
                $prefix . "is an empty string, which would produce an empty path segment: '/p/ads//edit'.",
            );
        }

        // Refused for the reason UrlGenerator::route() gives: the host decodes
        // the path exactly once, so a '/' is a separator again by the time the
        // request is routed, and the link would resolve to another route or
        // another row. No encoding avoids it -- a second layer would survive
        // into the router as a literal '%2F' in the key, which is not this
        // row's key either. Every row link a grid draws today goes through
        // route() and is refused the same way.
        if (str_contains($text, '/')) {
            throw new InvalidArgumentException(
                $prefix . "contains '/', which is a separator again the moment the host decodes the path, so "
                . 'the link would address a different row or no route at all. A row whose key contains a '
                . 'slash cannot be addressed in a path; expose a column holding a path-safe key.',
            );
        }

        if ($text === '.' || $text === '..') {
            throw new InvalidArgumentException(
                $prefix . "is the dot segment '{$text}', which every incoming path drops before a route is "
                . 'matched, so the link would resolve elsewhere.',
            );
        }

        // A brace in the data would reach UrlGenerator::reference() looking
        // exactly like a placeholder nobody substituted -- the value is passed
        // on raw, by design -- and be refused there with a message blaming the
        // template. Refusing it here can say which row and which column.
        if (str_contains($text, '{') || str_contains($text, '}')) {
            throw new InvalidArgumentException(
                $prefix . 'contains a brace, which the URL layer reads as a row placeholder nobody '
                . 'substituted and refuses. A row key cannot contain a brace.',
            );
        }

        return $text;
    }
}
