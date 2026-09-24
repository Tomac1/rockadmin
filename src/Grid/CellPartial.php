<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Page\ColumnType;
use RockAdmin\Page\Display;

/**
 * Which template partial draws a `CellView`'s own display.
 *
 * A grid row and a preview field both show one cell, decided by the very
 * same rule: a display picks its own partial when it draws something
 * distinctive (a badge, a checkbox, a progress bar); a plain cell falls
 * back to its type, because four of the seven types share `Display::Plain`
 * and would otherwise have nothing left to tell them apart by. That rule
 * used to be written out twice, byte for byte, in
 * `templates/region/list/row.php` and `templates/region/preview/field.php`
 * -- two copies of one mapping, which is exactly the shape rule 3 exists
 * to forbid, one layer up from the query drift it usually means. Both
 * templates call this instead.
 *
 * Every filename this returns follows the display it draws — `badge.php`
 * for `Display::Badge`, `check.php` for `Display::Check`, and so on — so a
 * project that wants to restyle every checkbox knows to copy
 * `region/list/cell/check.php` without reading this class's source. `Plain`
 * is the one display split further by type, because four types share it and
 * three of those (money, datetime, JSON) still deserve their own override
 * seam; the rest fall back to `plain.php`.
 *
 * Whether a cell links to its row is a separate question this method has no
 * part in: `$cell->url` decides that, independently of the display, and it
 * is `row.php` and `field.php` that choose `region/list/cell/link.php`
 * over whatever this method returns, wrapping it. See `cell/link.php`.
 *
 * A static method on a plain class, not a template of its own, because the
 * decision is a lookup with no markup — nothing here is a fragment anyone
 * should be able to open on its own, and a project overriding
 * `region/list/cell/plain.php` still only has to override that one file.
 */
final class CellPartial
{
    public static function templateFor(CellView $cell): string
    {
        return match ($cell->display) {
            Display::Badge => 'region/list/cell/badge',
            Display::Check => 'region/list/cell/check',
            Display::YesNo => 'region/list/cell/yesno',
            Display::Progress => 'region/list/cell/progress',
            Display::Percent => 'region/list/cell/percent',
            Display::Plain => match ($cell->type) {
                ColumnType::Money => 'region/list/cell/money',
                ColumnType::Datetime => 'region/list/cell/datetime',
                ColumnType::Json => 'region/list/cell/json',
                ColumnType::Text,
                ColumnType::Int,
                ColumnType::Bool,
                ColumnType::Enum => 'region/list/cell/plain',
            },
        };
    }
}
