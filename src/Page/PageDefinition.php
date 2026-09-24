<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use RockAdmin\Config\Schema;
use RockAdmin\Db\Entity;

/**
 * A loaded page: everything about it, built from its file.
 *
 * `$entity` is a real `RockAdmin\Db\Entity`, complete with its `Relation`
 * objects, so a query factory receives something the data layer already
 * understands rather than a bag of arrays it would have to interpret itself.
 */
final class PageDefinition
{
    /**
     * @param array<string, mixed>            $scope   column => value, always applied and never removable
     *                                                  by a URL — a placeholder such as `{{workspace.site_id}}`
     *                                                  survives unresolved, ready to bind per request
     * @param array<string, RegionDefinition> $regions keyed by the region's own key
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $layout,
        public readonly string $description,
        public readonly Entity $entity,
        public readonly array $scope,
        public readonly array $regions,
    ) {
    }

    public function region(string $key): RegionDefinition
    {
        if (isset($this->regions[$key])) {
            return $this->regions[$key];
        }

        $nearest = Schema::nearestOf(array_keys($this->regions), $key);
        $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

        throw new PageException("Page '{$this->name}' has no region '{$key}'.{$suffix}");
    }

    public function hasRegion(string $key): bool
    {
        return isset($this->regions[$key]);
    }
}
