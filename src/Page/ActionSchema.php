<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;
use RockAdmin\Config\SchemaKey;
use RockAdmin\Config\ValueType;

/**
 * The schema for one action in a page's `actions` block.
 *
 * An action is defined once and referenced wherever it should appear — a
 * column of the grid, the page header, the bulk bar — because a region cannot
 * know where it will be displayed, and that is specification 8.5's whole
 * argument for keeping the two apart.
 *
 * The schema says what keys exist and what type each holds. It cannot say
 * that a `post` must carry a `confirm`, or that a `when` may only name a
 * column the region declares: those are relations between keys and facts
 * about the rest of the page, and they are refused in `PageRepository`, where
 * the page is in hand.
 */
final class ActionSchema
{
    public static function create(): Schema
    {
        return new Schema([
            'type' => new SchemaKey(
                ValueType::String,
                default: 'link',
                description: 'What activating the action does: `link` navigates to it, `post` submits to it '
                    . 'and changes data. `open` loads a region into a modal or an offcanvas; it is in the '
                    . 'specification and refused at load until the milestone that builds it.',
                example: 'post',
            ),
            'label' => new SchemaKey(
                ValueType::String,
                description: 'The text on the control. Defaults at load to the key, first letter upper-cased, '
                    . 'underscores turned to spaces — so `archive` needs no label and `send_to_review` '
                    . 'probably wants one.',
                example: 'Archive',
            ),
            'icon' => new SchemaKey(
                ValueType::String,
                description: 'An icon name shown before the label, or instead of it in a narrow action '
                    . 'column. Set it when the action appears in a grid row, where there is rarely room '
                    . 'for words.',
                example: 'archive',
            ),
            // Mixed, not String, for the reason `options` is: `Resolver`
            // turns `@page:...` into a reference object before the validator
            // ever sees it, exactly as it does for `@enum:`, so the key holds
            // the reference rather than its text.
            'to' => new SchemaKey(
                ValueType::Mixed,
                description: 'Where the action goes, always as a reference and never as a bare URL: '
                    . '`@page:ads/{id}/edit` for a `link`, `@action:archive` for a `post`. A `{column}` in it '
                    . 'is substituted per row while rendering, URL-encoded, from that row\'s own data — not '
                    . 'to be confused with `{{user.id}}`, which resolves once at load from the signed-in '
                    . 'administrator. A raw URL is refused because it cannot be permission-checked against '
                    . 'a page.',
                example: '@page:ads/{id}/edit',
            ),
            'target' => new SchemaKey(
                ValueType::Mixed,
                description: 'What an `open` shows, always as a `@region:` reference and never as a bare '
                    . 'URL: `@region:detail` for a region of this page, `@region:users.detail` for one of '
                    . "another page's, which then loads under *that* page's permissions. A raw URL is "
                    . 'refused because it cannot be permission-checked against a page, and would become '
                    . 'the obvious way to smuggle content into the admin frame. Required on an `open` and '
                    . 'refused on anything else, which uses `to`.',
                example: '@region:detail',
            ),
            'in' => new SchemaKey(
                ValueType::String,
                description: 'Where an `open` puts what it loads: `modal` for a dialog over the page, '
                    . '`offcanvas` for a panel sliding in from the side. Required on an `open` — there is '
                    . 'no sensible default, and guessing one means finding out which you got by clicking. '
                    . 'Refused on a `link` or a `post`, neither of which opens anything.',
                example: 'offcanvas',
            ),
            'size' => new SchemaKey(
                ValueType::String,
                description: 'How wide the overlay is: `sm`, `md`, `lg` or `xl` for a modal, `sm` or `lg` '
                    . 'for an offcanvas. A size the stylesheet has no rule for is refused at load rather '
                    . 'than quietly drawing the default. Optional; each overlay has its own default.',
                example: 'lg',
            ),
            'title' => new SchemaKey(
                ValueType::String,
                description: 'The heading on the overlay, with row placeholders: `Ad #{id}`. Shown while '
                    . 'the fragment is still loading; once it arrives, a `data-ra-title` on its root wins, '
                    . 'because by then the answer knows which row it is about.',
                example: 'Ad #{id}',
            ),
            'params' => new SchemaKey(
                ValueType::Array,
                description: 'The query the fragment is fetched with, as a map of parameter to value, '
                    . 'where a value may be a `{column}` of the row the control was drawn on: '
                    . "`['id' => '{user_id}']` on an ads grid means that ad's seller. URL-encoded on "
                    . 'substitution, exactly as a `to` is. Not to be confused with `{{user.id}}`, which '
                    . 'resolves once at load to the signed-in administrator — a mistake that looks '
                    . 'correct in testing, because the developer is usually looking at their own records.',
                example: ['id' => '{id}'],
            ),
            'permission' => new SchemaKey(
                ValueType::String,
                description: 'The permission a reader needs before the action is drawn for them at all, and '
                    . 'before a `post` to it is accepted. One of the generated `page.action` names; anything '
                    . 'outside that catalogue is refused when the gate is built. Set it on every action that '
                    . 'changes data.',
                example: 'ads.update',
            ),
            'confirm' => new SchemaKey(
                ValueType::String,
                description: 'The question asked before a `post` runs. Required on every `post` — one that '
                    . 'changes data and asks nothing is a mis-click away from a deletion — and refused on a '
                    . '`link`, because confirming something that only navigates teaches people to dismiss '
                    . 'confirmations.',
                example: 'Archive this ad?',
            ),
            'when' => new SchemaKey(
                ValueType::Array,
                description: 'Row conditions deciding whether the action is offered: a map of column key to '
                    . 'the one value that column must hold. Every key must name a column the list region '
                    . 'declares. It decides what is *offered*, so it is re-checked on the server when the '
                    . 'action is performed — a client that was never shown it can still post it.',
                example: ['state' => 'active'],
            ),
            'set' => new SchemaKey(
                ValueType::Array,
                description: 'What a `post` writes: a map of column key to the one value to store, applied '
                    . 'to the row the action was drawn for. This is what makes `archive` mean something — '
                    . "`'set' => ['state' => 'archived']` — and every `post` needs it except one pointing "
                    . 'at `@action:delete`, which deletes instead. Values are literals, never expressions '
                    . 'and never row placeholders: an action that computes what it writes is a form, and '
                    . 'forms already exist. Refused on a `link`, which writes nothing.',
                example: ['state' => 'archived'],
            ),
            'refresh' => new SchemaKey(
                ValueType::Array,
                description: 'Which regions of this page re-render once the action completes, as a list of '
                    . 'region keys. Without it the page reloads. Set it when the action changes what one '
                    . 'region shows and nothing else.',
                example: ['grid'],
            ),
        ]);
    }
}
