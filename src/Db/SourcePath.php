<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * Where a column's value comes from.
 *
 *   title                  a column of the entity's own table
 *   user.name              a column reached through one declared relation
 *   user.company.name      through two, each declared by its full path
 *   stats->daily->views    a value inside a JSON column of this row
 *   user.profile->locale   both
 *
 * The two separators are deliberately different. A dot adds a join; an arrow
 * stays inside the row. Spelling them the same would hide the cost of a
 * column.
 */
final class SourcePath
{
    /**
     * @param ?string      $relation the full relation path, or null for this table
     * @param list<string> $json     keys to walk inside the column
     */
    private function __construct(
        public readonly ?string $relation,
        public readonly string $column,
        public readonly array $json,
    ) {
    }

    public static function parse(string $source): self
    {
        $parts = explode('->', $source);
        $head = array_shift($parts);

        foreach ($parts as $key) {
            if ($key === '') {
                throw new DbException("Source path '{$source}' has an empty JSON key.");
            }
        }

        if (str_contains(implode('->', $parts), '.')) {
            throw new DbException(
                "Source path '{$source}' mixes a dot into its JSON keys. A dot adds a join, "
                . 'so it cannot appear after an arrow.',
            );
        }

        $segments = explode('.', $head);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new DbException(
                    "Source path '{$source}' has an empty segment: dots separate a relation path from a column, "
                    . 'and each part between them must be non-empty.',
                );
            }
        }

        $column = array_pop($segments);
        $relation = $segments === [] ? null : implode('.', $segments);

        return new self($relation, $column, $parts);
    }

    /**
     * Every join this path needs, outermost first.
     *
     * 'user.company.name' needs 'user' before 'user.company', because the
     * second joins onto the table the first brought in.
     *
     * @return list<string>
     */
    public function joins(): array
    {
        if ($this->relation === null) {
            return [];
        }

        $joins = [];
        $prefix = '';

        foreach (explode('.', $this->relation) as $segment) {
            $prefix = $prefix === '' ? $segment : "{$prefix}.{$segment}";
            $joins[] = $prefix;
        }

        return $joins;
    }
}
