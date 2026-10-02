<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\HandlerReference;
use RockAdmin\Config\Schema;

/**
 * The fields a form region reads and writes, and how a copy differs from a
 * plain edit.
 */
final class FormDefinition
{
    /**
     * @param array<string, FieldDefinition> $fields keyed by the field's own key
     * @param list<string>                   $resetOnCopy field keys that fall back to their own default
     *                                                     when copying, instead of being carried over from
     *                                                     the source row
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $resetOnCopy = [],
        /**
         * The project's own logic for saving this form, or null for the
         * default. Carried as the reference rather than the object for the
         * reason an action's `to` is: a page definition is configuration, and
         * resolving it to something the host wired would make loading a page
         * depend on the host being wired first.
         */
        public readonly ?HandlerReference $handler = null,
    ) {
    }

    public function field(string $key): FieldDefinition
    {
        if (isset($this->fields[$key])) {
            return $this->fields[$key];
        }

        $nearest = Schema::nearestOf(array_keys($this->fields), $key);
        $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

        throw new PageException("Unknown field '{$key}' in this form.{$suffix}");
    }

    public function hasField(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    /**
     * The fields a submission may set: neither readonly nor hidden. A hidden
     * field's value comes from its default when the row is saved, never
     * from the wire, which is what stops a tampered form dropping a
     * workspace scope.
     *
     * @return array<string, FieldDefinition>
     */
    public function editable(): array
    {
        return array_filter(
            $this->fields,
            static fn (FieldDefinition $field): bool => !$field->readonly && !$field->hidden,
        );
    }
}
