<?php

declare(strict_types=1);

namespace RockAdmin\Form;

use RockAdmin\Page\FieldDefinition;

/**
 * How large a file may be and what it may contain, for one field.
 *
 * Two limits that exist in two places -- `uploads.*` in the installation's
 * configuration and `accept`/`max_bytes` on a field -- and one rule about how
 * they combine: **a field may narrow and may not widen**. A page file is
 * configuration a project writes, and if it could raise the ceiling the
 * installation-wide setting would be advice rather than a limit.
 *
 * So `forField()` takes the smaller number and the intersection, and a field
 * asking for a type the installation does not allow quietly gets nothing of
 * it -- quietly here rather than loudly, because the page loader cannot see
 * `rockadmin.php` (rule 4) and refusing at the moment somebody uploads would
 * mean a form that offers what it will not take.
 */
final class UploadLimits
{
    /**
     * @param list<string> $types content types, lower-cased
     */
    public function __construct(
        public readonly int $maxBytes = 5_242_880,
        public readonly array $types = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
        ],
    ) {
    }

    /** The limits this field actually enforces: the installation's, narrowed by its own. */
    public function forField(FieldDefinition $field): self
    {
        $types = $field->accept === []
            ? $this->types
            : array_values(array_intersect($this->types, $field->accept));

        return new self(
            min($this->maxBytes, $field->maxBytes ?? $this->maxBytes),
            $types,
        );
    }

    /** The limit in words, for a message somebody reads after being refused. */
    public function sizeInWords(): string
    {
        if ($this->maxBytes >= 1_048_576) {
            $megabytes = $this->maxBytes / 1_048_576;

            return rtrim(rtrim(number_format($megabytes, 1, '.', ''), '0'), '.') . ' MB';
        }

        return max(1, (int) round($this->maxBytes / 1024)) . ' kB';
    }

    /**
     * The types in words: `JPEG, PNG or PDF` rather than a list of media
     * types.
     *
     * A person who has just been refused needs to know what to upload
     * instead, and `image/jpeg` is not what their computer calls it.
     */
    public function typesInWords(): string
    {
        $names = [];

        foreach ($this->types as $type) {
            $names[] = strtoupper(substr($type, (int) strrpos($type, '/') + 1));
        }

        $names = array_values(array_unique($names));
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names) . ' or ' . $last;
    }
}
