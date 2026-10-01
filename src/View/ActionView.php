<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * One action as it will be drawn: the answer, never the question.
 *
 * Everything that had to be decided — whether this reader may perform it at
 * all, whether this row's values offer it, what URL it addresses once the row
 * placeholders are substituted, and the token a POST has to carry — was
 * decided by `ActionBuilder` before this object existed. A template that holds
 * one has nothing left to work out, which is the same contract `CellView` and
 * `RowView` keep and the reason rule 4 lets a template hold either.
 *
 * It lives in `View` rather than beside `CellView` in `Grid` because two
 * unrelated places draw one: a grid cell, and the page header's own buttons
 * (spec 8.5, "Where actions appear"). `PageView` carries a list of these, and
 * `View` cannot reach into `Grid` without the two namespaces depending on each
 * other — `Grid` already depends on this one.
 *
 * `$isPost` is a boolean rather than the `ActionType` it came from, and that is
 * deliberate: an `ActionType` is configuration, and the only thing a template
 * may ask is what to draw. A post is a form with a token; everything else is a
 * link.
 */
final class ActionView
{
    public function __construct(
        /** The action's own key: its identity class, and its template hook. */
        public readonly string $key,
        public readonly string $label,
        public readonly string $url,
        /** Whether activating it changes data, and so must POST and carry a token. */
        public readonly bool $isPost = false,
        /**
         * The question asked before a `post` runs, bound by `core.js` from
         * `data-ra-confirm`. Always set on a post — the loader refuses one
         * without it — and always empty on a link.
         */
        public readonly string $confirm = '',
        /** An icon name, or null to draw the label alone. */
        public readonly ?string $icon = null,
        /**
         * The CSRF token a post submits, empty for a link.
         *
         * It is on the view rather than read from a session by the template,
         * for the reason `FormView::$token` is: a template has no session, and
         * a token rendered from one place is a token the one write path can
         * verify.
         */
        public readonly string $token = '',
        /**
         * The key of the row this control acts on, or null when there is no
         * row behind it — a page header button, a bulk control.
         *
         * `POST /a/{page}/{verb}` names the page and the verb and not the
         * row, so a row's key travels in the body as `FormFields::ID`, the
         * same way a form's does. It is on the view because only
         * `ActionBuilder` has the row, and a template cannot ask for it.
         *
         * Without it every row-level `post` answered 404 — "this request
         * names no row to write" — which is the correct refusal to a request
         * that genuinely named none. The control was drawn, it was gated, it
         * carried a token and a confirmation, and it could not work. Four
         * tests passed over it because each one posted the key by hand.
         */
        public readonly ?string $rowKey = null,
        /**
         * For an `open`: the fragment URL, where to put it, how wide, and
         * what to call it while it loads. Empty on anything else.
         *
         * `$url` stays what it has always been — where the control goes — and
         * for an `open` that is the standalone page showing the same thing,
         * not the fragment. The control is an anchor that script upgrades,
         * never a button that script replaces: 8.6's own example is a button,
         * and a button does nothing at all with JavaScript off, which every
         * milestone in this project has been held against.
         */
        public readonly string $fragmentUrl = '',
        /** `modal` or `offcanvas`. */
        public readonly string $in = '',
        /** The overlay's width modifier, empty for the overlay's own default. */
        public readonly string $size = '',
        /** The heading shown while the fragment is in flight. */
        public readonly string $title = '',
        /**
         * The region keys that re-render once a `post` completes, instead of
         * the page reloading. Empty means reload, which is what every action
         * did before and what every action still does with no script.
         *
         * @var list<string>
         */
        public readonly array $refresh = [],
    ) {
    }

    /** Whether this control opens a region in an overlay. */
    public function isOpen(): bool
    {
        return $this->in !== '';
    }

    public function classes(): string
    {
        return Classes::of('action', $this->key);
    }
}
