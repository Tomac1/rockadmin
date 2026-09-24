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
use RockAdmin\Db\Connection;
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
 * `entity.relations` has a real per-entry schema (`table`, `on`, `type`), so
 * the generic Validator checks it like anything else. `entity.scope` and a
 * region's `sort` are declared with no `children`/`each` at all — their keys
 * are chosen by the project (a column name), not a fixed set the schema
 * could enumerate — so the Validator only checks they are arrays, and this
 * class does the rest by hand: a sort direction must be `asc` or `desc`,
 * which the schema cannot express since it only knows the value is a string.
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

    /**
     * The only alignments a page file may write. `left`/`right` are accepted
     * as aliases of `start`/`end` because specification 6.2's own example
     * writes `'align' => 'right'` — refusing it outright would leave the
     * specification's own example unloadable.
     */
    private const ALIGNMENTS = [
        'start' => 'start',
        'end' => 'end',
        'left' => 'start',
        'right' => 'end',
    ];

    /** @var array<string, PageDefinition> */
    private array $cache = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param Closure(string): ?string $env
     * @param ?Connection $connection only consulted when a region declares
     *                                `'fields' => '@all'` (spec 8.5), which
     *                                needs the table's real columns and only
     *                                the database knows them. A page that
     *                                never uses `@all` loads fine without one.
     */
    public function __construct(
        private readonly string $directory,
        private readonly Closure $env,
        private readonly Enums $enums,
        private readonly int $defaultPerPage = 25,
        private readonly ?Connection $connection = null,
    ) {
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
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
        $errors = (new Validator())->validate($resolved, $schema);

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
        $scope = $this->buildScope($entityConfig);

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

        // A preview region's fields (spec 8.5) are resolved in a second pass,
        // once every region -- including every list region a preview might
        // inherit from -- already exists. A single pass would make the
        // outcome depend on the order regions happen to be written in.
        $grid = $this->firstListRegion($regions);

        foreach ($regionsConfig as $regionKey => $regionConfig) {
            $region = $regions[(string) $regionKey] ?? null;

            if ($region === null || $region->type !== RegionType::Preview || !\is_array($regionConfig)) {
                continue;
            }

            /** @var array<string, mixed> $regionConfig */
            $fields = $this->resolveFields($name, $region->key, $regionConfig, $grid, $entity);

            $regions[$region->key] = new RegionDefinition(
                $region->key,
                $region->type,
                $region->perPage,
                $region->columns,
                $region->sort,
                $region->searchable,
                $fields,
                $region->searchPlaceholder,
            );
        }

        return new PageDefinition($name, $title, $layout, $description, $entity, $scope, $regions);
    }

    /** @param array<string, RegionDefinition> $regions */
    private function firstListRegion(array $regions): ?RegionDefinition
    {
        foreach ($regions as $region) {
            if ($region->type === RegionType::List) {
                return $region;
            }
        }

        return null;
    }

    /**
     * Which fields a preview shows, from spec 8.5 exactly: omitted inherits
     * the page's list region's columns, an explicit list names exactly those
     * in that order, `'@all'` expands to every column of the entity's table,
     * and `[]` is legal but shows nothing, which is almost always a mistake.
     *
     * @param  array<string, mixed> $regionConfig
     * @return list<ColumnDefinition>
     */
    private function resolveFields(
        string $pageName,
        string $regionKey,
        array $regionConfig,
        ?RegionDefinition $grid,
        Entity $entity,
    ): array {
        $hasFields = \array_key_exists('fields', $regionConfig) && $regionConfig['fields'] !== null;
        $raw = $hasFields ? $regionConfig['fields'] : null;

        if (!$hasFields) {
            return $this->fieldsFromGrid($pageName, $regionKey, $grid, static fn (RegionDefinition $g): array => array_values($g->columns));
        }

        if ($raw === '@all') {
            return $this->allEntityColumns($pageName, $regionKey, $entity, $grid);
        }

        if (\is_array($raw)) {
            if ($raw === []) {
                $this->warnings[] = "Page '{$pageName}': region '{$regionKey}' has fields => [], which shows "
                    . 'no fields at all. This is almost always a mistake.';

                return [];
            }

            return $this->fieldsFromGrid(
                $pageName,
                $regionKey,
                $grid,
                function (RegionDefinition $g) use ($pageName, $regionKey, $raw): array {
                    $fields = [];

                    foreach ($raw as $fieldKey) {
                        if (!\is_string($fieldKey) && !\is_int($fieldKey)) {
                            throw new PageException(\sprintf(
                                "Page '%s': region '%s' names a field as a %s. Field keys are strings.",
                                $pageName,
                                $regionKey,
                                get_debug_type($fieldKey),
                            ));
                        }

                        $fields[] = $this->fieldFromGrid($pageName, $regionKey, $g, (string) $fieldKey);
                    }

                    return $fields;
                },
            );
        }

        throw new PageException(\sprintf(
            "Page '%s': region '%s' has 'fields' set to a %s. Use a list of column keys or '@all'.",
            $pageName,
            $regionKey,
            get_debug_type($raw),
        ));
    }

    /**
     * @param  Closure(RegionDefinition): list<ColumnDefinition> $resolve
     * @return list<ColumnDefinition>
     */
    private function fieldsFromGrid(string $pageName, string $regionKey, ?RegionDefinition $grid, Closure $resolve): array
    {
        if ($grid === null) {
            throw new PageException(\sprintf(
                "Page '%s': region '%s' needs a list region on the same page to take its fields from, and "
                    . "the page has none. Give it its own 'fields' => '@all', or a list of column keys with "
                    . "each column's own definition.",
                $pageName,
                $regionKey,
            ));
        }

        return $resolve($grid);
    }

    private function fieldFromGrid(string $pageName, string $regionKey, RegionDefinition $grid, string $fieldKey): ColumnDefinition
    {
        if (isset($grid->columns[$fieldKey])) {
            return $grid->columns[$fieldKey];
        }

        $nearest = Schema::nearestOf(array_keys($grid->columns), $fieldKey);
        $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

        throw new PageException(\sprintf(
            "Page '%s': region '%s' names field '%s', which is not a column of the list region '%s'.%s",
            $pageName,
            $regionKey,
            $fieldKey,
            $grid->key,
            $suffix,
        ));
    }

    /** @return list<ColumnDefinition> */
    private function allEntityColumns(string $pageName, string $regionKey, Entity $entity, ?RegionDefinition $grid): array
    {
        if ($this->connection === null) {
            throw new PageException(\sprintf(
                "Page '%s': region '%s' has fields => '@all', which needs a database connection to list the "
                    . "columns of '%s'. Pass one to PageRepository.",
                $pageName,
                $regionKey,
                $entity->table,
            ));
        }

        $fields = [];

        foreach ($this->connection->columns($entity->table) as $columnName) {
            $fields[] = $grid !== null && isset($grid->columns[$columnName])
                ? $grid->columns[$columnName]
                : $this->defaultColumn($columnName);
        }

        return $fields;
    }

    /** A column named by '@all' that no list region already describes: shown plainly, as text. */
    private function defaultColumn(string $columnName): ColumnDefinition
    {
        return new ColumnDefinition(
            filter: null,
            collection: null,
            key: $columnName,
            label: $this->labelFromKey($columnName),
            source: $columnName,
            type: ColumnType::Text,
            display: Display::Plain,
            sortable: false,
            link: false,
            align: 'start',
            width: null,
            class: '',
        );
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
            /** @var array<string, mixed> $relationConfig each entry is an array: PageSchema's `each` guarantees it */
            $relationConfig = \is_array($relationConfig) ? $relationConfig : [];
            $relations[$relationName] = $this->buildRelation($pageName, $relationName, $relationConfig);
        }

        try {
            return new Entity($table, $key, $relations);
        } catch (DbException $e) {
            throw new PageException("Page '{$pageName}': {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * `table` and `on` are guaranteed present and string-typed by PageSchema
     * (required, ValueType::String) before this ever runs — checking them
     * again here would be the same rule enforced twice, which is exactly
     * what lets the two drift. Only the join type is genuinely this class's
     * job: the schema knows `type` is a string, not that it is `left` or
     * `inner`.
     *
     * @param array<string, mixed> $relationConfig
     */
    private function buildRelation(string $pageName, string $relationName, array $relationConfig): Relation
    {
        $table = \is_string($relationConfig['table'] ?? null) ? $relationConfig['table'] : '';
        $on = \is_string($relationConfig['on'] ?? null) ? $relationConfig['on'] : '';

        $typeValue = \is_string($relationConfig['type'] ?? null) ? $relationConfig['type'] : JoinType::Left->value;
        $joinType = JoinType::tryFrom($typeValue);

        if ($joinType === null) {
            $names = array_map(static fn (JoinType $t): string => $t->value, JoinType::cases());

            throw new PageException(\sprintf(
                "Page '%s': relation '%s' has an unknown join type '%s'. The types are: '%s'.",
                $pageName,
                $relationName,
                $typeValue,
                implode("', '", $names),
            ));
        }

        return new Relation($relationName, $table, $on, $joinType);
    }

    /**
     * @param  array<string, mixed> $entityConfig
     * @return array<string, mixed> column => value, placeholders included and unresolved
     */
    private function buildScope(array $entityConfig): array
    {
        $raw = \is_array($entityConfig['scope'] ?? null) ? $entityConfig['scope'] : [];
        $scope = [];

        foreach ($raw as $column => $value) {
            $scope[(string) $column] = $value;
        }

        return $scope;
    }

    /** @param array<string, mixed> $regionConfig */
    private function buildRegion(string $pageName, string $regionKey, array $regionConfig, Entity $entity): RegionDefinition
    {
        $typeValue = \is_string($regionConfig['type'] ?? null) ? $regionConfig['type'] : '';

        try {
            $type = RegionType::parse($typeValue);
        } catch (PageException $e) {
            throw new PageException(
                "Page '{$pageName}': region '{$regionKey}': {$e->getMessage()}",
                previous: $e,
            );
        }

        $perPage = \is_int($regionConfig['per_page'] ?? null) ? $regionConfig['per_page'] : $this->defaultPerPage;

        if ($perPage < 1) {
            throw new PageException(\sprintf(
                "Page '%s': region '%s' has per_page %d. A page needs at least one row.",
                $pageName,
                $regionKey,
                $perPage,
            ));
        }

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

            // A URL's sort naming an unselected column is silently dropped by
            // QueryBuilder — it arrived from a link nobody wrote by hand. A
            // region's own default sort is different: a person wrote this,
            // in the page file, so an undeclared column here is refused
            // outright, the same way a field naming one already is.
            if (!isset($columns[$sortColumn])) {
                $nearest = Schema::nearestOf(array_keys($columns), $sortColumn);
                $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

                throw new PageException(\sprintf(
                    "Page '%s': region '%s' has a default sort naming '%s', which is not a column of this "
                        . 'region.%s',
                    $pageName,
                    $regionKey,
                    $sortColumn,
                    $suffix,
                ));
            }

            // A collection's values come from a supplementary query, so its
            // alias never reaches the select list and QueryBuilder drops any
            // sort naming it — the same dead configuration a filter or a
            // searchable on one would be. The grid would simply order by the
            // tiebreaker and nobody would be told why.
            if ($columns[$sortColumn]->collection !== null) {
                throw new PageException(\sprintf(
                    "Page '%s': region '%s' sorts by '%s', which is a collection. A collection is fetched "
                        . 'separately from the rows, so the database cannot order by it.',
                    $pageName,
                    $regionKey,
                    $sortColumn,
                ));
            }

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

        /** @var array<string, mixed> $searchConfig */
        $searchConfig = \is_array($regionConfig['search'] ?? null) ? $regionConfig['search'] : [];
        $searchPlaceholder = \is_string($searchConfig['placeholder'] ?? null) ? $searchConfig['placeholder'] : null;

        $form = null;

        if ($type === RegionType::Form) {
            if (!\is_array($regionConfig['form'] ?? null)) {
                throw new PageException(\sprintf(
                    "Page '%s': region '%s' is a form, but declares no 'form' block.",
                    $pageName,
                    $regionKey,
                ));
            }

            /** @var array<string, mixed> $formConfig */
            $formConfig = $regionConfig['form'];
            $form = $this->buildForm($pageName, $regionKey, $formConfig);
        }

        return new RegionDefinition(
            $regionKey,
            $type,
            $perPage,
            $columns,
            $sort,
            $searchable,
            searchPlaceholder: $searchPlaceholder,
            form: $form,
        );
    }

    /** @param array<string, mixed> $formConfig */
    private function buildForm(string $pageName, string $regionKey, array $formConfig): FormDefinition
    {
        /** @var array<string, mixed> $fieldsConfig */
        $fieldsConfig = \is_array($formConfig['fields'] ?? null) ? $formConfig['fields'] : [];
        $fields = [];

        foreach ($fieldsConfig as $fieldKey => $fieldConfig) {
            $fieldKey = (string) $fieldKey;

            if (!\is_array($fieldConfig)) {
                throw new PageException(\sprintf(
                    "Page '%s': field '%s' must be an array, got %s.",
                    $pageName,
                    $fieldKey,
                    get_debug_type($fieldConfig),
                ));
            }

            /** @var array<string, mixed> $fieldConfig */
            $fields[$fieldKey] = $this->buildField($pageName, $fieldKey, $fieldConfig);
        }

        /** @var array<string, mixed> $copyConfig */
        $copyConfig = \is_array($formConfig['copy'] ?? null) ? $formConfig['copy'] : [];
        $resetRaw = \is_array($copyConfig['reset'] ?? null) ? $copyConfig['reset'] : [];
        $resetOnCopy = [];

        foreach ($resetRaw as $resetKey) {
            if (!\is_string($resetKey) && !\is_int($resetKey)) {
                throw new PageException(\sprintf(
                    "Page '%s': region '%s' names a copy.reset entry as a %s. Field keys are strings.",
                    $pageName,
                    $regionKey,
                    get_debug_type($resetKey),
                ));
            }

            $resetKey = (string) $resetKey;

            if (!isset($fields[$resetKey])) {
                $nearest = Schema::nearestOf(array_keys($fields), $resetKey);
                $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

                throw new PageException(\sprintf(
                    "Page '%s': region '%s' has copy.reset naming '%s', which is not a field of this form.%s",
                    $pageName,
                    $regionKey,
                    $resetKey,
                    $suffix,
                ));
            }

            $resetOnCopy[] = $resetKey;
        }

        return new FormDefinition($fields, $resetOnCopy);
    }

    /** @param array<string, mixed> $fieldConfig */
    private function buildField(string $pageName, string $fieldKey, array $fieldConfig): FieldDefinition
    {
        $typeValue = \is_string($fieldConfig['type'] ?? null) ? $fieldConfig['type'] : 'text';

        try {
            $type = FieldType::parse($typeValue);
        } catch (PageException $e) {
            throw new PageException(
                "Page '{$pageName}': field '{$fieldKey}': {$e->getMessage()}",
                previous: $e,
            );
        }

        $label = \is_string($fieldConfig['label'] ?? null) && $fieldConfig['label'] !== ''
            ? $fieldConfig['label']
            : $this->labelFromKey($fieldKey);

        $hasOptions = \array_key_exists('options', $fieldConfig) && $fieldConfig['options'] !== null;

        if ($hasOptions && !$type->takesOptions()) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' has 'options', but type '%s' does not take options.",
                $pageName,
                $fieldKey,
                $type->value,
            ));
        }

        if (!$hasOptions && $type->takesOptions()) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' has type '%s', which needs 'options', but none were given.",
                $pageName,
                $fieldKey,
                $type->value,
            ));
        }

        $options = $hasOptions ? $this->resolveOptions($fieldConfig['options'], $pageName, $fieldKey) : [];

        $required = ($fieldConfig['required'] ?? false) === true;
        $readonly = ($fieldConfig['readonly'] ?? false) === true;
        $hidden = ($fieldConfig['hidden'] ?? false) === true;

        if ($required && $readonly) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' is both 'required' and 'readonly', which asks for a value the form "
                    . 'will never send.',
                $pageName,
                $fieldKey,
            ));
        }

        $help = \is_string($fieldConfig['help'] ?? null) ? $fieldConfig['help'] : '';
        $placeholder = \is_string($fieldConfig['placeholder'] ?? null) ? $fieldConfig['placeholder'] : '';

        $min = \is_int($fieldConfig['min'] ?? null) ? $fieldConfig['min'] : null;
        $max = \is_int($fieldConfig['max'] ?? null) ? $fieldConfig['max'] : null;

        if ($min !== null && $max !== null && $min > $max) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' has min %d greater than max %d.",
                $pageName,
                $fieldKey,
                $min,
                $max,
            ));
        }

        $step = \is_string($fieldConfig['step'] ?? null) ? $fieldConfig['step'] : null;
        $rows = \is_int($fieldConfig['rows'] ?? null) ? $fieldConfig['rows'] : null;
        $pattern = \is_string($fieldConfig['pattern'] ?? null) ? $fieldConfig['pattern'] : null;

        if ($pattern !== null && @preg_match('~' . $pattern . '~', '') === false) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' has an invalid pattern '%s'.",
                $pageName,
                $fieldKey,
                $pattern,
            ));
        }

        return new FieldDefinition(
            key: $fieldKey,
            label: $label,
            type: $type,
            default: $fieldConfig['default'] ?? null,
            required: $required,
            readonly: $readonly,
            hidden: $hidden,
            help: $help,
            placeholder: $placeholder,
            options: $options,
            min: $min,
            max: $max,
            step: $step,
            rows: $rows,
            pattern: $pattern,
        );
    }

    /** @param array<string, mixed> $columnConfig */
    private function buildColumn(string $pageName, string $columnKey, array $columnConfig, Entity $entity): ColumnDefinition
    {
        $typeValue = \is_string($columnConfig['type'] ?? null) ? $columnConfig['type'] : 'text';

        try {
            $type = ColumnType::parse($typeValue);
        } catch (PageException $e) {
            throw new PageException(
                "Page '{$pageName}': column '{$columnKey}': {$e->getMessage()}",
                previous: $e,
            );
        }

        $displayValue = $columnConfig['display'] ?? null;

        try {
            $display = \is_string($displayValue) ? Display::parse($displayValue) : $type->defaultDisplay();
        } catch (PageException $e) {
            throw new PageException(
                "Page '{$pageName}': column '{$columnKey}': {$e->getMessage()}",
                previous: $e,
            );
        }

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

        if ($hasCollection) {
            $this->assertNotDeadOnACollection($pageName, $columnKey, $columnConfig);
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
            ? $this->resolveAlign($pageName, $columnKey, $columnConfig['align'])
            : $type->defaultAlignment();
        $width = \is_string($columnConfig['width'] ?? null) ? $columnConfig['width'] : null;
        $class = \is_string($columnConfig['class'] ?? null) ? $columnConfig['class'] : '';

        $enumOptions = \array_key_exists('options', $columnConfig)
            ? $this->resolveOptions($columnConfig['options'], $pageName, $columnKey)
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

    /**
     * A collection column's values come from a supplementary query (rule 8),
     * never from the row's own select list — so its alias is never among
     * the expressions `QueryBuilder` filters, sorts or searches against.
     * `filter`, `sortable` and `searchable` on such a column are all
     * accepted and all silently dead: a labelled filter box nobody can ever
     * narrow by, a sort link that never reorders anything, a search box
     * that never matches it. Refused at load, by name, the same way
     * `source` + `collection` together already are.
     *
     * @param array<string, mixed> $columnConfig
     */
    private function assertNotDeadOnACollection(string $pageName, string $columnKey, array $columnConfig): void
    {
        if (\is_array($columnConfig['filter'] ?? null)) {
            throw new PageException(\sprintf(
                "Page '%s': column '%s' carries both 'filter' and 'collection'. A collection's values are "
                    . "never in the query's select list, so a filter naming it can never match anything.",
                $pageName,
                $columnKey,
            ));
        }

        if (($columnConfig['sortable'] ?? false) === true) {
            throw new PageException(\sprintf(
                "Page '%s': column '%s' is 'sortable' and carries 'collection'. A collection's values are "
                    . 'never in the query\'s select list, so a sort naming it is silently dropped.',
                $pageName,
                $columnKey,
            ));
        }

        if (($columnConfig['searchable'] ?? false) === true) {
            throw new PageException(\sprintf(
                "Page '%s': column '%s' is 'searchable' and carries 'collection'. A collection's values are "
                    . 'never in the query\'s select list, so the search box can never match it.',
                $pageName,
                $columnKey,
            ));
        }
    }

    private function resolveAlign(string $pageName, string $columnKey, string $align): string
    {
        if (!isset(self::ALIGNMENTS[$align])) {
            $nearest = Schema::nearestOf(array_keys(self::ALIGNMENTS), $align);
            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new PageException(\sprintf(
                "Page '%s': column '%s' has an unknown align '%s'. Use 'start', 'end', 'left' or 'right'.%s",
                $pageName,
                $columnKey,
                $align,
                $suffix,
            ));
        }

        return self::ALIGNMENTS[$align];
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
            $options = $this->resolveOptions($filterConfig['options'], $pageName, $columnKey);
        }

        return new FilterDefinition($type, $operator, $label, $options, $placeholder);
    }

    /**
     * Accepts an `@enum:` reference, or a literal map from value to either a
     * label string or `['label' => ..., 'color' => ...]`. Anything else is
     * refused by name: an entry that is neither of those is almost always a
     * typo, and dropping it would produce a filter quietly missing a choice.
     *
     * @return array<string, EnumOption>
     */
    private function resolveOptions(mixed $raw, string $pageName, string $columnKey): array
    {
        if ($raw instanceof EnumReference) {
            try {
                return $this->enums->options($raw);
            } catch (ConfigException $e) {
                throw new PageException(
                    "Page '{$pageName}': column '{$columnKey}': {$e->getMessage()}",
                    previous: $e,
                );
            }
        }

        if (!\is_array($raw)) {
            throw new PageException(
                "Page '{$pageName}': column '{$columnKey}': options must be an @enum: reference or a map "
                . 'of value to label, not a ' . get_debug_type($raw) . '.',
            );
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

                continue;
            }

            // Dropping this silently is how a mistyped 'lable' becomes an
            // option that never appears in a filter, with nothing anywhere
            // saying why. Every other malformed thing in a page file is
            // refused by name; so is this.
            throw new PageException(
                "Page '{$pageName}': column '{$columnKey}': the option '{$value}' is neither a label nor "
                . "['label' => ..., 'color' => ...].",
            );
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
