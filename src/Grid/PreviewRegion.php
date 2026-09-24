<?php

declare(strict_types=1);

namespace RockAdmin\Grid;

use RockAdmin\Db\CountStrategy;
use RockAdmin\Db\Filter;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\Page as DbPage;
use RockAdmin\Db\Query;
use RockAdmin\Db\RowSource;
use RockAdmin\Page\ColumnDefinition;
use RockAdmin\Page\ColumnType;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\RegionDefinition;

/**
 * Turns a described preview region and a row's key into a `PreviewView` a
 * template can render: one row, one field per declared column.
 *
 * A preview reuses the grid's own query machinery rather than reading a row
 * a second way: it builds a `RockAdmin\Db\Query` naming the same source
 * paths `CellFormatter` already knows how to format, filtered on the
 * entity's key and paged to one row, and hands it to the same `RowSource`
 * `ListRegion` reads from. Two readers of the same table that disagreed
 * about what a column means is exactly the drift rule 3 of this project
 * exists to prevent — see `PreviewRegionTest::testThePreviewAndTheGridFormatTheSameValueIdentically()`.
 *
 * `QueryFactory::build()` takes a `GridState` — filters, search, sort and a
 * page number read out of a URL — which a preview of one known row has no
 * use for, so it is not called here. A preview builds its own `Query`
 * directly, reusing `RockAdmin\Db\Query`, `Filter` and `RowSource`, the
 * lower machinery `QueryFactory` itself is built from.
 */
final class PreviewRegion
{
    /**
     * A field's formatted text past this many characters gets its own
     * full-width row instead of squeezing into a label-and-value column,
     * the same way a JSON field always does.
     */
    private const int WIDE_TEXT_LENGTH = 80;

    public function __construct(
        private readonly RowSource $rows,
        private readonly CellFormatter $cells,
    ) {
    }

    /** Null when no row has that key — the caller turns that into a 404. */
    public function render(PageDefinition $page, RegionDefinition $region, string $id): ?PreviewView
    {
        $key = $page->entity->key;

        // The entity's key is always selected, whether or not the region's
        // own fields name it, because the filter below matches against it —
        // QueryBuilder drops a filter naming an alias the query never
        // selected, and a preview that silently ignored its own id would
        // show whichever row the database happened to return first.
        $columns = [$key => $key];
        $collections = [];

        foreach ($region->fields as $field) {
            // A field inherited from the grid (the common case: 'fields' is
            // omitted) may name a one-to-many column -- the same one the
            // grid fetches once for the whole page of rows, per rule 8. A
            // preview of one row fetches its own collections the same way,
            // through Query::$collections, rather than trying to read one
            // out of the entity's own columns, which is what a naive
            // key => source map would have done to a column that has no
            // source at all.
            if ($field->collection !== null) {
                $collections[] = $field->collection;

                continue;
            }

            $columns[$field->key] = $field->source;
        }

        $query = new Query(
            entity: $page->entity,
            columns: $columns,
            scope: $page->scope,
            filters: [new Filter($key, FilterOperator::Equals, $id)],
            page: DbPage::of(1, 1),
            count: CountStrategy::None,
            collections: $collections,
        );

        $result = $this->rows->fetch($query);
        $row = $result->rows[0] ?? null;

        if ($row === null) {
            return null;
        }

        $fields = $this->fieldViews($region, $row);

        return new PreviewView(
            key: $region->key,
            title: $this->title($fields, $id),
            id: $id,
            fields: $fields,
        );
    }

    /**
     * @param  array<string, mixed> $row
     * @return list<FieldView>
     */
    private function fieldViews(RegionDefinition $region, array $row): array
    {
        $views = [];

        foreach ($region->fields as $field) {
            $cell = $this->cells->format($field, $row[$field->key] ?? null);

            $views[] = new FieldView($field->key, $field->label, $cell, $this->isWide($field, $cell));
        }

        return $views;
    }

    private function isWide(ColumnDefinition $field, CellView $cell): bool
    {
        return $field->type === ColumnType::Json || mb_strlen($cell->text) > self::WIDE_TEXT_LENGTH;
    }

    /**
     * The row's own label: the first field's own formatted text, which is
     * usually a name or a title, rather than anything the page itself is
     * called. A field with nothing to show, or no fields at all — spec 8.5
     * allows `'fields' => []` — falls back to the id, so a preview title is
     * never blank.
     *
     * @param list<FieldView> $fields
     */
    private function title(array $fields, string $id): string
    {
        $first = $fields[0] ?? null;

        if ($first !== null && $first->cell->text !== '') {
            return $first->cell->text;
        }

        return '#' . $id;
    }
}
