<?php

declare(strict_types=1);

namespace RockAdmin\Form;

/**
 * The body keys a form carries that are not columns of the entity.
 *
 * A template writes these and an action handler reads them back, which makes
 * them a contract between two pieces of code that never call each other.
 * `Csrf::FIELD` already exists for exactly that reason; these are its
 * siblings, and without them each side would spell the string from memory.
 *
 * The leading underscore keeps them clear of a column genuinely named `id` or
 * `ret`. A field key may not begin with one, so a submission can never carry
 * a value that lands on the same name.
 */
final class FormFields
{
    /** The key of the row being written, for a form that edits or copies one. */
    public const ID = '_id';

    /** Where to send the person once the write succeeds. */
    public const RETURN_TO = '_ret';

    /**
     * The keys a bulk action applies to, as `_ids[]`.
     *
     * Plural and separate from `ID` rather than overloading it. A handler that
     * read one key as a list of one would have no way to tell "this row" from
     * "the one row that happened to be selected", and the two take different
     * paths: a single action redirects on the row it named, a bulk action
     * reports how many of a selection it applied to.
     */
    public const IDS = '_ids';

    /**
     * The marker that says "this is a bulk submission", whatever is selected.
     *
     * An unchecked checkbox sends nothing, so a selection form submitted with
     * nothing ticked carries no `_ids` at all — identical in the body to a
     * request that was never a bulk one. Without this, pressing a bulk action
     * with nothing selected would be read as a single-row action naming no
     * row and answer 404, when the true answer is "nothing was selected".
     *
     * A hidden input the browser always sends is the only thing that can
     * tell the two apart, because the difference is the absence of data.
     */
    public const BULK = '_bulk';

    /** @return list<string> every reserved key, for a refusal that names them all */
    public static function reserved(): array
    {
        return [self::ID, self::IDS, self::BULK, self::RETURN_TO];
    }
}
