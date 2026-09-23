<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use Closure;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Defaults;
use RockAdmin\Config\Definitions;
use RockAdmin\Config\EnumOption;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Resolver;
use RockAdmin\Config\Schema;
use RockAdmin\Config\ValidationError;
use RockAdmin\Config\Validator;
use RockAdmin\Db\Collection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\Entity;
use RockAdmin\Db\FilterOperator;
use RockAdmin\Db\JoinType;
use RockAdmin\Db\Relation;
use RockAdmin\Db\Sort;
use RockAdmin\Db\SortDirection;
use RockAdmin\Db\SourcePath;

/**
 * Reads a directory of page files and turns each into a PageDefinition.
 *
 * The pass order repeats RockAdmin\Config\Loader::load(): expand shared
 * definitions, resolve placeholders, validate, apply defaults, then build the
 * objects the rest of the code reads. Validation runs before defaults so it
 * judges what a project actually wrote.
 *
 * `entity.relations`, `entity.scope`, and a region's `sort` and `search` are
 * declared in PageSchema with an empty child schema — their keys are chosen
 * by the project (a relation name, a column name), not a fixed set the
 * schema could enumerate. The generic Validator has no "any keys" mode: an
 * empty child schema means "no keys allowed", which would reject every real
 * page. Those four blocks are therefore type-checked and swapped for an
 * empty array before the generic pass runs, and parsed by hand afterwards —
 * relations and sort with the domain rules this class documents; scope and
 * search are only checked to be arrays, because PageDefinition does not yet
 * expose them (see the task report).
 */
final class PageRepository
{
    private const FILTER_DEFAULT_OPERATORS = [
        'text' => 'contains',
        'select' => 'equals',
        'multiselect' => 'in',
        'range' => 'between',
        'date' => 'between',
        'boolean' => 'equals',
    ];

    /** @var array<string, PageDefinition> */
    private array $cache = [];

    /** @param Closure(string): ?string $env */
    public function __construct(
        private readonly string $directory,
        private readonly Closure $env,
        private readonly Enums $enums,
        private readonly int $defaultPerPage = 25,
    ) {
    }

    public function has(string $name): bool
    {
        return is_file($this->pagePath($name));
    }

    public function get(string $name): PageDefinition
    {
        return $this->cache[$name] ??= $this->load($name);
    }

    /** @return list<string> */
    public function names(): array
    {
        $files = glob($this->directory . '/*.php') ?: [];
        $names = array_map(static fn (string $file): string => basename($file, '.php'), $files);
        sort($names);

        return $names;
    }

    private function load(string $name): PageDefinition
    {
        $file = $this->pagePath($name);

        if (!is_file($file)) {
            throw new PageException("Page '{$name}' not found in {$this->directory}.");
        }

        try {
            $raw = $this->readFile($file);
            $definitions = new Definitions($this->definitionsFrom($this->readFile($this->definitionsPath())));
            $expanded = $definitions->expand($raw);

            $resolver = new Resolver($this->env, $raw);
            $resolved = $resolver->resolve($expanded);
        } catch (ConfigException $e) {
            throw new PageException("Page '{$name}': {$e->getMessage()}", previous: $e);
        }

        $schema = PageSchema::create();
        $errors = (new Validator())->validate($this->forValidation($resolved), $schema);

        if ($errors !== []) {
            throw new PageException($this->report($name, $file, $errors));
        }

        $withDefaults = (new Defaults())->apply($resolved, $schema);

        return $this->build($name, $withDefaults);
    }

    private function pagePath(string $name): string
    {
        return $this->directory . '/' . $name . '.php';
    }

    private function definitionsPath(): string
    {
        return \dirname($this->directory) . '/defs.php';
    }

    /** @return array<string, mixed> */
    private function readFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = require $path;

        if (!\is_array($contents)) {
            throw new PageException("{$path} must return an array, got " . get_debug_type($contents) . '.');
        }

        /** @var array<string, mixed> $contents narrows array<mixed, mixed> — return.type without it */
        return $contents;
    }

    /**
     * @param  array<string, mixed> $raw
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function definitionsFrom(array $raw): array
    {
        $definitions = [];

        foreach ($raw as $namespace => $entries) {
            if (!\is_array($entries)) {
                throw new PageException("Definition namespace '{$namespace}' must be an array.");
            }

            foreach ($entries as $key => $definition) {
                if (!\is_array($definition)) {
                    throw new PageException("Definition '@{$namespace}:{$key}' must be an array.");
                }

                /** @var array<string, mixed> $definition narrows array<mixed, mixed> — return.type without it */
                $definitions[(string) $namespace][(string) $key] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * A validation-safe copy: the freeform blocks the schema cannot describe
     * are type-checked here and then emptied, so the generic pass does not
     * reject their project-chosen keys.
     *
     * @param  array<string, mixed> $resolved
     * @return array<string, mixed>
     */
    private function forValidation(array $resolved): array
    {
        $copy = $resolved;

        if (\is_array($copy['entity'] ?? null)) {
            /** @var array<string, mixed> $entity */
            $entity = $copy['entity'];
            $this->assertArrayIfPresent($entity, 'relations', 'entity.relations');
            $this->assertArrayIfPresent($entity, 'scope', 'entity.scope');

            if (\array_key_exists('relations', $entity)) {
                $entity['relations'] = [];
            }

            if (\array_key_exists('scope', $entity)) {
                $entity['scope'] = [];
            }

            $copy['entity'] = $entity;
        }

        if (\is_array($copy['regions'] ?? null)) {
            /** @var array<string, mixed> $regions */
            $regions = $copy['regions'];

            foreach ($regions as $regionKey => $region) {
                if (!\is_array($region)) {
                    continue;
                }

                /** @var array<string, mixed> $region narrows array<mixed, mixed> — argument.type without it */
                $this->assertArrayIfPresent($region, 'sort', "regions.{$regionKey}.sort");
                $this->assertArrayIfPresent($region, 'search', "regions.{$regionKey}.search");

                if (\array_key_exists('sort', $region)) {
                    $region['sort'] = [];
                }

                if (\array_key_exists('search', $region)) {
                    $region['search'] = [];
                }

                $regions[$regionKey] = $region;
            }

            $copy['regions'] = $regions;
        }

        return $copy;
    }

    /** @param array<string, mixed> $node */
    private function assertArrayIfPresent(array $node, string $key, string $path): void
    {
        if (\array_key_exists($key, $node) && !\is_array($node[$key])) {
            throw new PageException("{$path}: Expected array, got " . get_debug_type($node[$key]) . '.');
        }
    }

    /** @param list<ValidationError> $errors */
    private function report(string $name, string $file, array $errors): string
    {
        $lines = array_map(static fn (ValidationError $e): string => "  {$e->path}: {$e->message}", $errors);

        return \sprintf(
            "Page '%s' (%s) is invalid:%s%s",
            $name,
            $file,
            PHP_EOL,
            implode(PHP_EOL, $lines),
        );
    }

    /** @param array<string, mixed> $config */
    private function build(string $name, array $config): PageDefinition
    {
        $title = \is_string($config['title'] ?? null) ? $config['title'] : '';
        $layout = \is_string($config['layout'] ?? null) ? $config['layout'] : 'single';

        $description = '';
        $header = $config['header'] ?? null;

        if (\is_array($header) && \is_string($header['description'] ?? null)) {
            $description = $header['description'];
        }

        /** @var array<string, mixed> $entityConfig */
        $entityConfig = \is_array($config['entity'] ?? null) ? $config['entity'] : [];
        $entity = $this->buildEntity($name, $entityConfig);

        /** @var array<string, mixed> $regionsConfig */
        $regionsConfig = \is_array($config['regions'] ?? null) ? $config['regions'] : [];
        $regions = [];

        foreach ($regionsConfig as $regionKey => $regionConfig) {
            if (!\is_array($regionConfig)) {
                continue;
            }

            /** @var array<string, mixed> $regionConfig */
            $regions[(string) $regionKey] = $this->buildRegion($name, (string) $regionKey, $regionConfig, $entity);
        }

        return new PageDefinition($name, $title, $layout, $description, $entity, $regions);
    }

    /** @param array<string, mixed> $entityConfig */
    private function buildEntity(string $pageName, array $entityConfig): Entity
    {
        $table = $entityConfig['table'] ?? null;

        if (!\is_string($table) || $table === '') {
            throw new PageException(
                "Page '{$pageName}' has no entity.table. A list region needs a table to read rows from.",
            );
        }

        $key = \is_string($entityConfig['key'] ?? null) ? $entityConfig['key'] : 'id';

        /** @var array<string, mixed> $relationsConfig */
        $relationsConfig = \is_array($entityConfig['relations'] ?? null) ? $entityConfig['relations'] : [];
        $relations = [];

        foreach ($relationsConfig as $relationName => $relationConfig) {
            $relationName = (string) $relationName;
            $relations[$relationName] = $this->buildRelation($pageName, $relationName, $relationConfig);
        }

        try {
            return new Entity($table, $key, $relations);
        } catch (DbException $e) {
            throw new PageException("Page '{$pageName}': {$e->getMessage()}", previous: $e);
        }
    }

    private function buildRelation(string $pageName, string $relationName, mixed $relationConfig): Relation
    {
        if (!\is_array($relationConfig)) {
            throw new PageException(\sprintf(
                "Page '%s': relation '%s' must be an array, got %s.",
                $pageName,
                $relationName,
                get_debug_type($relationConfig),
            ));
        }

        $table = $relationConfig['table'] ?? null;
        $on = $relationConfig['on'] ?? null;

        if (!\is_string($table) || $table === '') {
            throw new PageException("Page '{$pageName}': relation '{$relationName}' needs a 'table'.");
        }

        if (!\is_string($on) || $on === '') {
            throw new PageException("Page '{$pageName}': relation '{$relationName}' needs an 'on' condition.");
        }

        $typeValue = $relationConfig['type'] ?? JoinType::Left->value;
        $joinType = \is_string($typeValue) ? JoinType::tryFrom($typeValue) : null;

        if ($joinType === null) {
            $names = array_map(static fn (JoinType $t): string => $t->value, JoinType::cases());

            throw new PageException(\sprintf(
                "Page '%s': relation '%s' has an unknown join type '%s'. The types are: '%s'.",
                $pageName,
                $relationName,
                \is_string($typeValue) ? $typeValue : get_debug_type($typeValue),
                implode("', '", $names),
            ));
        }

        return new Relation($relationName, $table, $on, $joinType);
    }

    /** @param array<string, mixed> $regionConfig */
    private function buildRegion(string $pageName, string $regionKey, array $regionConfig, Entity $entity): RegionDefinition
    {
        $type = \is_string($regionConfig['type'] ?? null) ? $regionConfig['type'] : '';
        $perPage = \is_int($regionConfig['per_page'] ?? null) ? $regionConfig['per_page'] : $this->defaultPerPage;

        /** @var array<string, mixed> $columnsConfig */
        $columnsConfig = \is_array($regionConfig['columns'] ?? null) ? $regionConfig['columns'] : [];
        $columns = [];
        $searchable = [];

        foreach ($columnsConfig as $columnKey => $columnConfig) {
            $columnKey = (string) $columnKey;

            if (!\is_array($columnConfig)) {
                throw new PageException(\sprintf(
                    "Page '%s': column '%s' must be an array, got %s.",
                    $pageName,
                    $columnKey,
                    get_debug_type($columnConfig),
                ));
            }

            /** @var array<string, mixed> $columnConfig */
            $columns[$columnKey] = $this->buildColumn($pageName, $columnKey, $columnConfig, $entity);

            if (($columnConfig['searchable'] ?? false) === true) {
                $searchable[] = $columnKey;
            }
        }

        /** @var array<string, mixed> $sortConfig */
        $sortConfig = \is_array($regionConfig['sort'] ?? null) ? $regionConfig['sort'] : [];
        $sort = [];

        foreach ($sortConfig as $sortColumn => $direction) {
            $sortColumn = (string) $sortColumn;
            $directionValue = \is_string($direction) ? $direction : '';
            $sortDirection = SortDirection::tryFrom($directionValue);

            if ($sortDirection === null) {
                throw new PageException(\sprintf(
                    "Page '%s': sort direction '%s' for '%s' is unknown. Use 'asc' or 'desc'.",
                    $pageName,
                    $directionValue,
                    $sortColumn,
                ));
            }

            $sort[] = new Sort($sortColumn, $sortDirection);
        }

        return new RegionDefinition($regionKey, $type, $perPage, $columns, $sort, $searchable);
    }

    /** @param array<string, mixed> $columnConfig */
    private function buildColumn(string $pageName, string $columnKey, array $columnConfig, Entity $entity): ColumnDefinition
    {
        $typeValue = \is_string($columnConfig['type'] ?? null) ? $columnConfig['type'] : 'text';
        $type = ColumnType::parse($typeValue);

        $displayValue = $columnConfig['display'] ?? null;
        $display = \is_string($displayValue) ? Display::parse($displayValue) : $type->defaultDisplay();

        if (!$type->allows($display)) {
            throw new PageException(\sprintf(
                "Page '%s': column '%s' has type '%s', which does not allow display '%s'.",
                $pageName,
                $columnKey,
                $type->value,
                $display->value,
            ));
        }

        $label = \is_string($columnConfig['label'] ?? null) && $columnConfig['label'] !== ''
            ? $columnConfig['label']
            : $this->labelFromKey($columnKey);

        $hasSource = \array_key_exists('source', $columnConfig) && $columnConfig['source'] !== null;
        $hasCollection = \array_key_exists('collection', $columnConfig) && $columnConfig['collection'] !== null;

        if ($hasSource && $hasCollection) {
            throw new PageException(\sprintf(
                "Page '%s': column '%s' carries both 'source' and 'collection'. A collection column gets its "
                    . 'values from a supplementary query, not the select list, so the two cannot both be true.',
                $pageName,
                $columnKey,
            ));
        }

        $source = $hasSource && \is_string($columnConfig['source']) ? $columnConfig['source'] : $columnKey;

        $collection = null;

        if ($hasCollection) {
            /** @var array<string, mixed> $collectionConfig */
            $collectionConfig = \is_array($columnConfig['collection']) ? $columnConfig['collection'] : [];
            $collection = new Collection(
                $columnKey,
                \is_string($collectionConfig['table'] ?? null) ? $collectionConfig['table'] : '',
                \is_string($collectionConfig['foreign_key'] ?? null) ? $collectionConfig['foreign_key'] : '',
                \is_string($collectionConfig['column'] ?? null) ? $collectionConfig['column'] : '',
            );
        } else {
            $this->assertSourceReachable($pageName, $columnKey, $source, $entity);
        }

        $sortable = ($columnConfig['sortable'] ?? false) === true;
        $link = ($columnConfig['link'] ?? false) === true;
        $align = \is_string($columnConfig['align'] ?? null) && $columnConfig['align'] !== ''
            ? $columnConfig['align']
            : $type->defaultAlignment();
        $width = \is_string($columnConfig['width'] ?? null) ? $columnConfig['width'] : null;
        $class = \is_string($columnConfig['class'] ?? null) ? $columnConfig['class'] : '';

        $enumOptions = \array_key_exists('options', $columnConfig)
            ? $this->resolveOptions($columnConfig['options'])
            : [];

        $options = [];

        if (\is_string($columnConfig['currency'] ?? null)) {
            $options['currency'] = $columnConfig['currency'];
        }

        if (\is_string($columnConfig['format'] ?? null)) {
            $options['format'] = $columnConfig['format'];
        }

        if (\is_int($columnConfig['max'] ?? null)) {
            $options['max'] = $columnConfig['max'];
        }

        if ($enumOptions !== []) {
            $options['enum'] = $enumOptions;
        }

        $filter = null;
        $filterConfig = $columnConfig['filter'] ?? null;

        if (\is_array($filterConfig)) {
            /** @var array<string, mixed> $filterConfig */
            $filter = $this->buildFilter($pageName, $columnKey, $filterConfig, $label, $enumOptions);
        }

        return new ColumnDefinition(
            filter: $filter,
            collection: $collection,
            key: $columnKey,
            label: $label,
            source: $source,
            type: $type,
            display: $display,
            sortable: $sortable,
            link: $link,
            align: $align,
            width: $width,
            class: $class,
            options: $options,
        );
    }

    private function assertSourceReachable(string $pageName, string $columnKey, string $source, Entity $entity): void
    {
        try {
            $path = SourcePath::parse($source);
        } catch (DbException $e) {
            throw new PageException(
                "Page '{$pageName}': column '{$columnKey}' has an invalid source: {$e->getMessage()}",
                previous: $e,
            );
        }

        foreach ($path->joins() as $relationName) {
            if (!$entity->hasRelation($relationName)) {
                $nearest = Schema::nearestOf(array_keys($entity->relations), $relationName);
                $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

                throw new PageException(\sprintf(
                    "Page '%s': column '%s' has source '%s' naming relation '%s', which the entity does not "
                        . 'declare.%s',
                    $pageName,
                    $columnKey,
                    $source,
                    $relationName,
                    $suffix,
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filterConfig
     * @param  array<string, EnumOption> $columnOptions
     */
    private function buildFilter(
        string $pageName,
        string $columnKey,
        array $filterConfig,
        string $columnLabel,
        array $columnOptions,
    ): FilterDefinition {
        $type = \is_string($filterConfig['type'] ?? null) && $filterConfig['type'] !== ''
            ? $filterConfig['type']
            : 'text';

        if (!isset(self::FILTER_DEFAULT_OPERATORS[$type])) {
            $nearest = Schema::nearestOf(array_keys(self::FILTER_DEFAULT_OPERATORS), $type);
            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new PageException(\sprintf(
                "Page '%s': column '%s' has an unknown filter type '%s'.%s",
                $pageName,
                $columnKey,
                $type,
                $suffix,
            ));
        }

        $opValue = \is_string($filterConfig['op'] ?? null) ? $filterConfig['op'] : self::FILTER_DEFAULT_OPERATORS[$type];
        $operator = FilterOperator::tryFrom($opValue);

        if ($operator === null) {
            $names = array_map(static fn (FilterOperator $o): string => $o->value, FilterOperator::cases());

            throw new PageException(\sprintf(
                "Page '%s': column '%s' has an unknown filter operator '%s'. The operators are: '%s'.",
                $pageName,
                $columnKey,
                $opValue,
                implode("', '", $names),
            ));
        }

        $label = \is_string($filterConfig['label'] ?? null) && $filterConfig['label'] !== ''
            ? $filterConfig['label']
            : $columnLabel;

        $placeholder = \is_string($filterConfig['placeholder'] ?? null) ? $filterConfig['placeholder'] : null;

        $options = $columnOptions;

        if (\array_key_exists('options', $filterConfig) && $filterConfig['options'] !== null) {
            $options = $this->resolveOptions($filterConfig['options']);
        }

        return new FilterDefinition($type, $operator, $label, $options, $placeholder);
    }

    /** @return array<string, EnumOption> */
    private function resolveOptions(mixed $raw): array
    {
        if ($raw instanceof EnumReference) {
            return $this->enums->options($raw);
        }

        if (!\is_array($raw)) {
            return [];
        }

        $options = [];

        foreach ($raw as $value => $entry) {
            $value = (string) $value;

            if (\is_string($entry)) {
                $options[$value] = new EnumOption($value, $entry);

                continue;
            }

            if (\is_array($entry) && \is_string($entry['label'] ?? null)) {
                $color = \is_string($entry['color'] ?? null) ? $entry['color'] : null;
                $options[$value] = new EnumOption($value, $entry['label'], $color);
            }
        }

        return $options;
    }

    /**
     * `created_at` becomes "Created at": a grid header is a phrase, not a
     * title, so only the first letter is upper-cased.
     */
    private function labelFromKey(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
