<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;

/**
 * Which template partial draws a `CellView`.
 *
 * A grid row and a preview field both show one cell, decided by the very
 * same rule: a display picks its own partial when it draws something
 * distinctive (a badge, a checkbox, a progress bar, a link); a plain cell
 * falls back to its type, because four of the seven types share
 * `Display::Plain` and would otherwise have nothing left to tell them
 * apart by. That rule used to be written out twice, byte for byte, in
 * `templates/region/list/row.php` and `templates/region/preview/field.php`
 * -- two copies of one mapping, which is exactly the shape rule 3 exists
 * to forbid, one layer up from the query drift it usually means. Both
 * templates call this instead.
 *
 * A static method on a plain class, not a template of its own, because the
 * decision is a lookup with no markup — nothing here is a fragment anyone
 * should be able to open on its own, and a project overriding
 * `region/list/cell/text.php` still only has to override that one file.
 */
final class CellPartial
{
    public static function templateFor(CellView $cell): string
    {
        return match ($cell->display) {
            Display::Badge => 'region/list/cell/enum',
            Display::Check, Display::YesNo => 'region/list/cell/bool',
            Display::Progress, Display::Percent => 'region/list/cell/int',
            Display::Link => 'region/list/cell/link',
            Display::Plain => match ($cell->type) {
                ColumnType::Money => 'region/list/cell/money',
                ColumnType::Datetime => 'region/list/cell/datetime',
                ColumnType::Json => 'region/list/cell/json',
                ColumnType::Int => 'region/list/cell/int',
                ColumnType::Text,
                ColumnType::Bool,
                ColumnType::Enum => 'region/list/cell/text',
            },
        };
    }
}
