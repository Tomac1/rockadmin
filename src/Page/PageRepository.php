<?php

declare(strict_types=1);

namespace RockAdmin\Page;

use Closure;
use RockAdmin\Config\ActionReference;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Defaults;
use RockAdmin\Config\Definitions;
use RockAdmin\Config\EnumOption;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\Enums;
use RockAdmin\Config\HandlerReference;
use RockAdmin\Config\PageReference;
use RockAdmin\Config\RegionReference;
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
use RockAdmin\Form\DefaultValue;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormFields;

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
    /**
     * The widths each overlay actually has a rule for.
     *
     * Read from `assets/css/ds/components.css`, where `.ra-modal--sm`
     * through `--xl` and `.ra-offcanvas--sm`/`--lg` are defined. A size
     * outside this list styles no element and does nothing while reading as
     * though it had been set — which is the `ra-btn--xs` mistake, found by
     * somebody looking at a flat button rather than by any test.
     *
     * `left` and `right` are offcanvas modifiers too and are deliberately not
     * here: which side a panel comes from is the template's business, not a
     * per-action choice, and offering it would mean two controls opening the
     * same region from opposite sides.
     *
     * @var array<string, list<string>>
     */
    private const array OVERLAY_SIZES = [
        'modal' => ['sm', 'md', 'lg', 'xl'],
        'offcanvas' => ['sm', 'lg'],
    ];

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

    /**
     * Refuses a `@page:` reference naming a page this directory does not hold.
     *
     * `Resolver` parsed the reference, because the syntax lives in `Config`;
     * it could not check it, because the catalogue of pages lives here and
     * handing the configuration layer a page repository would have it reach
     * into the layer that consumes its output — the same thing `Loader` could
     * not do for a role name, resolved the same way.
     *
     * The nearest name is `Schema::nearestOf()`, as every other suggestion in
     * this class is: a page reference is one more list of names somebody can
     * mistype, not a reason for a fifth copy of the same Levenshtein call.
     *
     * @param string $where what carries the reference, e.g. "page 'ads': action 'edit'"
     */
    public function assertPageExists(PageReference $reference, string $where): void
    {
        if ($this->has($reference->page)) {
            return;
        }

        $nearest = Schema::nearestOf($this->names(), $reference->page);
        $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

        throw new PageException(\sprintf(
            "%s points at '%s', which is not a page in %s.%s",
            $where,
            (string) $reference,
            $this->directory,
            $suffix,
        ));
    }

    /**
     * That `@region:users.detail` names a region of a page that has one.
     *
     * Only the cross-page form reaches here. `@region:detail` addresses the
     * page being built, whose regions may not exist yet — an action inside a
     * column is built while that column's own region still is — and loading
     * the page to ask would be a recursion into the load that is running.
     *
     * The page is loaded, which means a reference to another page pulls that
     * page in at load time. That is the same cost `@page:` already pays
     * through `assertPageExists()`, and it is the price of refusing a dead
     * reference at load rather than at click time, where it is an empty
     * overlay with nothing to read.
     */
    public function assertRegionExists(RegionReference $reference, string $where): void
    {
        $page = $reference->page;

        if ($page === null) {
            return;
        }

        if (!$this->has($page)) {
            $nearest = Schema::nearestOf($this->names(), $page);

            throw new PageException(\sprintf(
                "%s points at '%s', and '%s' is not a page in %s.%s",
                $where,
                (string) $reference,
                $page,
                $this->directory,
                $nearest === null ? '' : " Did you mean '{$nearest}'?",
            ));
        }

        $definition = $this->get($page);

        if ($definition->hasRegion($reference->region)) {
            return;
        }

        $nearest = Schema::nearestOf(array_keys($definition->regions), $reference->region);

        throw new PageException(\sprintf(
            "%s points at '%s', and page '%s' has no region '%s'. It has: '%s'.%s",
            $where,
            (string) $reference,
            $page,
            $reference->region,
            implode("', '", array_keys($definition->regions)),
            $nearest === null ? '' : " Did you mean '{$nearest}'?",
        ));
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

        /** @var array<string, mixed> $actionsConfig */
        $actionsConfig = \is_array($config['actions'] ?? null) ? $config['actions'] : [];

        // Read before any region is built, because an action defined inside a
        // column's `items` is built while its own region still is. See
        // `ActionContext`.
        $context = ActionContext::of($regionsConfig);

        $regions = [];

        foreach ($regionsConfig as $regionKey => $regionConfig) {
            if (!\is_array($regionConfig)) {
                continue;
            }

            /** @var array<string, mixed> $regionConfig */
            $regions[(string) $regionKey] = $this->buildRegion(
                $name,
                (string) $regionKey,
                $regionConfig,
                $entity,
                $actionsConfig,
                $context,
            );
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

        $actions = [];

        foreach ($actionsConfig as $actionKey => $actionConfig) {
            if (!\is_array($actionConfig)) {
                continue;
            }

            /** @var array<string, mixed> $actionConfig */
            $actions[(string) $actionKey] = $this->buildAction(
                $name,
                (string) $actionKey,
                $actionConfig,
                $context,
            );
        }

        $headerButtons = \is_array($header)
            ? $this->buildActionItems($name, 'header button', $header['buttons'] ?? null, $actionsConfig, $context, true)
            : [];

        // Not `rowless`: a bulk action's `when` names a column and is tested
        // per selected row when it runs, which is a real condition and not the
        // header button's "there is no row here at all". What a bulk action
        // may not have is a row placeholder in its `to`, and that is refused
        // below, where the reason can be given.
        $bulkActions = $this->buildActionItems(
            $name,
            'bulk action',
            $config['bulk_actions'] ?? null,
            $actionsConfig,
            $context,
            false,
        );

        foreach ($bulkActions as $bulk) {
            $this->assertBulkIsPerformable($name, $bulk);
        }

        return new PageDefinition(
            $name,
            $title,
            $layout,
            $description,
            $entity,
            $scope,
            $regions,
            $actions,
            $headerButtons,
            $bulkActions,
        );
    }

    /**
     * The two things a bulk action may not be.
     *
     * **It may not be a `link`.** A selection of forty rows has no single URL
     * to navigate to, so a `link` in `bulk_actions` would draw a control that
     * either goes nowhere or goes to one arbitrary row of the selection.
     *
     * **Its `to` may not carry a `{column}`.** A row placeholder is
     * substituted per row while rendering, and a bulk control is drawn once
     * for the whole table — there is no row to substitute from. Left
     * unrefused it would render the literal braces into a URL and 404 at
     * click time, with nothing saying why.
     */
    private function assertBulkIsPerformable(string $pageName, ActionDefinition $action): void
    {
        $where = "Page '{$pageName}': bulk action '{$action->key}'";

        if (!$action->type->isWrite()) {
            throw new PageException(
                "{$where} is a '{$action->type->value}'. A bulk action applies to a selection of rows and "
                . "so must be a 'post': a selection has no single URL to navigate to, and a control that "
                . 'went to one arbitrary row of forty would look like it worked.',
            );
        }

        if (str_contains((string) $action->to, '{')) {
            throw new PageException(
                "{$where} has a row placeholder in its 'to'. A {column} is substituted per row while "
                . 'rendering, and a bulk control is drawn once for the whole table — there is no row to '
                . 'substitute from. The selection travels in the body instead.',
            );
        }
    }

    /**
     * An `items` list, or a `header.buttons` list, in the three forms
     * specification 8.5 gives — a named action, a named action adjusted here,
     * and one defined inline — mixed in any order.
     *
     * **Every form goes through `buildAction()`.** That is the whole shape of
     * this method: it decides which configuration array an entry stands for and
     * hands it over, so an inline `post` with no `confirm`, an inline `when` on
     * a column nobody declared and an inline bare URL are refused by the five
     * refusals that already exist, rather than by a second copy of them that
     * would drift. A named entry is looked up in the page's own raw `actions`
     * block and built again from it; building it twice costs nothing and means
     * there is one construction path rather than a cache that has to be
     * populated in the right order.
     *
     * An adjusted entry merges its own keys over the named action's, overrides
     * winning and a whole key at a time: `when` is one value, and merging two
     * `when` maps would produce a third condition set neither author wrote.
     *
     * @param  string                 $what        names the carrier in every refusal: "column 'tools'"
     * @param  array<string, mixed>   $pageActions the page's own `actions` block, raw
     * @param  bool                   $rowless     true where there is no row behind the control
     * @return list<ActionDefinition>
     */
    private function buildActionItems(
        string $pageName,
        string $what,
        mixed $items,
        array $pageActions,
        ActionContext $context,
        bool $rowless,
    ): array {
        if (!\is_array($items)) {
            return [];
        }

        $where = "Page '{$pageName}': {$what}";
        $built = [];

        foreach ($items as $index => $item) {
            [$key, $config] = $this->actionItem($where, $index, $item, $pageActions);
            $built[] = $this->buildAction($pageName, $key, $config, $context, $what, $rowless);
        }

        return $built;
    }

    /**
     * Which of the three forms one entry is, and what action configuration it
     * therefore stands for.
     *
     * @param  array<string, mixed>                 $pageActions
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function actionItem(string $where, int|string $index, mixed $item, array $pageActions): array
    {
        if (\is_string($item)) {
            return [$item, $this->namedAction($where, $item, $pageActions)];
        }

        if (!\is_array($item)) {
            throw new PageException(\sprintf(
                '%s has an entry that is a %s. An entry names an action of this page, adjusts one with '
                . "'use' => '@action:...', or defines one inline under a key of its own.",
                $where,
                get_debug_type($item),
            ));
        }

        /** @var array<string, mixed> $item */
        if (\array_key_exists('use', $item)) {
            $reference = $item['use'];

            if (!$reference instanceof ActionReference) {
                throw new PageException(\sprintf(
                    "%s has a 'use' of %s. An entry adjusted here names the action it starts from as "
                    . "'@action:edit' — an action of this page, never a page or a shared definition.",
                    $where,
                    \is_string($reference) ? "'{$reference}'" : get_debug_type($reference),
                ));
            }

            unset($item['use']);

            return [
                $reference->action,
                array_replace($this->namedAction($where, $reference->action, $pageActions), $item),
            ];
        }

        if (\is_int($index)) {
            throw new PageException(\sprintf(
                '%s defines an action inline with no key, so nothing names it — and its key is what becomes '
                . "its CSS class and what a permission is read against. Write it as 'log' => [...], or use "
                . "'use' => '@action:...' to start from one the page already declares.",
                $where,
            ));
        }

        return [$index, $item];
    }

    /**
     * @param  array<string, mixed>  $pageActions
     * @return array<string, mixed>
     */
    private function namedAction(string $where, string $key, array $pageActions): array
    {
        $config = $pageActions[$key] ?? null;

        if (!\is_array($config)) {
            $nearest = Schema::nearestOf(array_map(strval(...), array_keys($pageActions)), $key);
            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new PageException(
                "{$where} names the action '{$key}', which this page's 'actions' block does not "
                . "declare.{$suffix}",
            );
        }

        /** @var array<string, mixed> $config */
        return $config;
    }

    /**
     * One action, with everything about it a page file may get wrong.
     *
     * Three of the refusals are about the action alone and could live
     * anywhere; two are not. A `when` names a column, and only the region
     * knows its columns; a `refresh` names a region, and only the page knows
     * its regions. Both are here for the same reason `@page:` is validated in
     * this class and not in `Resolver`: parse where the syntax is, validate
     * where the knowledge is. The permission catalogue is the third of that
     * kind and is *not* here — see `Permission::assertActionsAreGrantable()`,
     * which lives in `Auth` because `Auth` depends on `Page` and the reverse
     * would be a cycle.
     *
     * @param array<string, mixed> $actionConfig
     * @param ?string              $within what carries this action, for the refusals to name: null for the
     *                                      page's own `actions` block, "column 'tools'" or 'header button'
     *                                      for an action reached through an `items` list
     * @param bool                 $rowless true where the control is drawn once with no row behind it, so a
     *                                      `when` and a `{column}` placeholder can never mean anything
     */
    private function buildAction(
        string $pageName,
        string $actionKey,
        array $actionConfig,
        ActionContext $context,
        ?string $within = null,
        bool $rowless = false,
    ): ActionDefinition {
        $where = "Page '{$pageName}': "
            . ($within === null ? '' : "{$within}: ")
            . "action '{$actionKey}'";
        $typeValue = \is_string($actionConfig['type'] ?? null) ? $actionConfig['type'] : 'link';

        try {
            $type = ActionType::parse($typeValue);
        } catch (PageException $e) {
            throw new PageException("{$where}: {$e->getMessage()}", previous: $e);
        }

        $to = $this->actionTarget($where, $type, $actionConfig, $rowless);
        $confirm = \is_string($actionConfig['confirm'] ?? null) ? $actionConfig['confirm'] : '';

        if ($type->isWrite() && $confirm === '') {
            throw new PageException(
                "{$where} is a 'post' with no 'confirm'. A POST that changes data and asks nothing is one "
                . 'mis-click from a deletion, so specification 8.5 confirms every one. Give it the question '
                . "to ask, such as 'confirm' => 'Archive this ad?'.",
            );
        }

        if (!$type->isWrite() && $confirm !== '') {
            throw new PageException(\sprintf(
                "%s is a '%s' with a 'confirm'. It changes nothing, and a confirmation in front of "
                . 'something that changes nothing teaches people to dismiss confirmations — including the '
                . "ones in front of a 'post'. Remove it, or make the action a 'post'.",
                $where,
                $type->value,
            ));
        }

        $label = \is_string($actionConfig['label'] ?? null) && $actionConfig['label'] !== ''
            ? $actionConfig['label']
            : $this->labelFromKey($actionKey);

        $handler = $this->handlerReference($where, $actionConfig['handler'] ?? null);
        $set = $this->actionSet($where, $type, $to, $actionConfig, $handler !== null);
        [$in, $size] = $this->actionOverlay($where, $type, $actionConfig);
        $title = \is_string($actionConfig['title'] ?? null) ? $actionConfig['title'] : '';

        if ($title !== '' && !$type->isOpen()) {
            throw new PageException(\sprintf(
                "%s is a '%s' with a 'title'. A title names an overlay's heading, and only an 'open' "
                . "opens one; the text on a control is its 'label'.",
                $where,
                $type->value,
            ));
        }

        if ($rowless && preg_match('/\{[a-z][a-z0-9_]*}/', $title, $match) === 1) {
            throw new PageException(\sprintf(
                "%s has a 'title' of '%s', and there is no row here to read '%s' from. A {column} is "
                . 'substituted from the row a control was drawn on, and this control is not drawn on one.',
                $where,
                $title,
                $match[0],
            ));
        }

        return new ActionDefinition(
            key: $actionKey,
            label: $label,
            type: $type,
            to: $to,
            icon: \is_string($actionConfig['icon'] ?? null) ? $actionConfig['icon'] : '',
            permission: \is_string($actionConfig['permission'] ?? null) ? $actionConfig['permission'] : '',
            confirm: $confirm,
            when: $this->actionConditions($where, $actionConfig, $context, $rowless),
            set: $set,
            refresh: $this->actionRefresh($where, $actionConfig, $context),
            in: $in,
            size: $size,
            title: $title,
            params: $this->actionParams($where, $type, $actionConfig, $rowless),
            handler: $handler,
        );
    }

    /**
     * Where an `open` puts what it loads, and how wide.
     *
     * The sizes are checked against what the stylesheet actually defines,
     * because a modifier nothing styles does nothing while reading like it
     * works — the `ra-btn--xs` mistake, which shipped and was found by
     * somebody looking at a flat button rather than by any of 2179 tests.
     * They differ per overlay: a modal has four widths and an offcanvas two.
     *
     * @param  array<string, mixed>     $actionConfig
     * @return array{0: string, 1: string} in, size
     */
    private function actionOverlay(string $where, ActionType $type, array $actionConfig): array
    {
        $in = \is_string($actionConfig['in'] ?? null) ? $actionConfig['in'] : '';
        $size = \is_string($actionConfig['size'] ?? null) ? $actionConfig['size'] : '';

        if (!$type->isOpen()) {
            foreach (['in', 'size'] as $key) {
                if (($actionConfig[$key] ?? null) !== null) {
                    throw new PageException(\sprintf(
                        "%s is a '%s' with an '%s'. Only an 'open' puts something in an overlay; a '%s' "
                        . '%s.',
                        $where,
                        $type->value,
                        $key,
                        $type->value,
                        $type->isWrite() ? 'submits and comes back' : 'navigates',
                    ));
                }
            }

            return ['', ''];
        }

        if ($in === '') {
            throw new PageException(
                "{$where} is an 'open' with no 'in'. It has to say where what it loads appears: "
                . "'in' => 'modal' for a dialog over the page, or 'in' => 'offcanvas' for a panel from "
                . 'the side. There is no default, because guessing one means finding out which you got '
                . 'by clicking.',
            );
        }

        if (!isset(self::OVERLAY_SIZES[$in])) {
            $nearest = Schema::nearestOf(array_keys(self::OVERLAY_SIZES), $in);

            throw new PageException(\sprintf(
                "%s has 'in' => '%s'. An overlay is '%s'.%s",
                $where,
                $in,
                implode("' or '", array_keys(self::OVERLAY_SIZES)),
                $nearest === null ? '' : " Did you mean '{$nearest}'?",
            ));
        }

        if ($size !== '' && !\in_array($size, self::OVERLAY_SIZES[$in], true)) {
            throw new PageException(\sprintf(
                "%s asks for a '%s' of size '%s', which the stylesheet does not define, so it would draw "
                . "the default width while reading as though it had been set. A %s is '%s'.",
                $where,
                $in,
                $size,
                $in,
                implode("', '", self::OVERLAY_SIZES[$in]),
            ));
        }

        return [$in, $size];
    }

    /**
     * The query an `open` fetches its fragment with.
     *
     * Values stay as written, placeholders and all: a `{column}` is
     * substituted per row while the region renders, by the same
     * `RowPlaceholders` that guards a `to`, and substituting it here would
     * mean doing it once for every row at load time against no row at all.
     *
     * A rowless control — a header button — may not carry one, for the reason
     * it may not carry a `when`: there is no row to read a `{column}` from,
     * and the literal braces would reach the URL.
     *
     * @param  array<string, mixed>  $actionConfig
     * @return array<string, string>
     */
    private function actionParams(
        string $where,
        ActionType $type,
        array $actionConfig,
        bool $rowless,
    ): array {
        $raw = $actionConfig['params'] ?? null;

        if ($raw === null) {
            return [];
        }

        if (!$type->isOpen()) {
            throw new PageException(\sprintf(
                "%s is a '%s' with 'params'. Only an 'open' fetches a fragment to put in an overlay, and "
                . "'params' is the query it fetches it with. A '%s' carries what it needs in its own "
                . "reference: '@page:ads/{id}/edit'.",
                $where,
                $type->value,
                $type->value,
            ));
        }

        if (!\is_array($raw)) {
            throw new PageException(\sprintf(
                "%s has 'params' => %s. It is a map of query parameter to value, such as "
                . "['id' => '{id}'].",
                $where,
                get_debug_type($raw),
            ));
        }

        $params = [];

        foreach ($raw as $name => $value) {
            if (!\is_scalar($value)) {
                throw new PageException(\sprintf(
                    "%s passes '%s' as a %s. A parameter is a string, a number, or a {column} of the row "
                    . 'the control was drawn on.',
                    $where,
                    (string) $name,
                    get_debug_type($value),
                ));
            }

            $text = \is_bool($value) ? ($value ? '1' : '0') : (string) $value;

            if ($rowless && preg_match('/\{[a-z][a-z0-9_]*}/', $text) === 1) {
                throw new PageException(\sprintf(
                    "%s passes '%s' => '%s', and there is no row here to read it from. A {column} is "
                    . 'substituted from the row a control was drawn on, and this control is not drawn on '
                    . 'one.',
                    $where,
                    (string) $name,
                    $text,
                ));
            }

            $params[(string) $name] = $text;
        }

        return $params;
    }

    /**
     * A `@handler:` reference, or null.
     *
     * Parsed by `Resolver` before this sees it, so anything arriving as a
     * plain string is by that fact not a reference -- the same test
     * `actionTarget()` makes, for the same reason: a bare name would be a
     * name this layer had to invent rules for, and there is already a syntax
     * for naming things.
     *
     * Whether anything is *registered* under it is `Operations`' business,
     * which is where that catalogue is.
     */
    private function handlerReference(string $where, mixed $raw): ?HandlerReference
    {
        if ($raw === null) {
            return null;
        }

        if (!$raw instanceof HandlerReference) {
            throw new PageException(\sprintf(
                "%s has 'handler' => %s, which is not a handler reference. A project's own logic is "
                . "named the way everything else is: 'handler' => '@handler:save_ad'.",
                $where,
                \is_string($raw) ? "'{$raw}'" : get_debug_type($raw),
            ));
        }

        return $raw;
    }

    /**
     * A file field's own limits, and the refusal when they belong to another
     * kind of field.
     *
     * Declared on something that is not a `file` they would sit in the page
     * file doing nothing, which is the silently-dead configuration this
     * loader exists to catch -- the same rule `pattern` on a checkbox already
     * follows.
     *
     * Whether the types a field asks for are ones the installation allows is
     * *not* decided here: `uploads.types` is in `rockadmin.php` and a page
     * repository does not read it. The write path narrows one by the other,
     * and a field asking for more than the installation allows gets less.
     *
     * @param  array<string, mixed>        $fieldConfig
     * @return array{0: list<string>, 1: ?int}
     */
    private function fileLimits(string $pageName, string $fieldKey, FieldType $type, array $fieldConfig): array
    {
        $accept = [];

        foreach (\is_array($fieldConfig['accept'] ?? null) ? $fieldConfig['accept'] : [] as $entry) {
            if (\is_string($entry) && $entry !== '') {
                $accept[] = strtolower(trim($entry));
            }
        }

        $maxBytes = \is_int($fieldConfig['max_bytes'] ?? null) ? $fieldConfig['max_bytes'] : null;

        if ($type !== FieldType::File && ($accept !== [] || $maxBytes !== null)) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' is a '%s' and declares 'accept' or 'max_bytes', which only a "
                    . "'file' field reads. Either the type is wrong or the keys are.",
                $pageName,
                $fieldKey,
                $type->value,
            ));
        }

        if ($maxBytes !== null && $maxBytes <= 0) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' has a max_bytes of %d, which accepts nothing. Leave it out to "
                    . "use the installation's own limit.",
                $pageName,
                $fieldKey,
                $maxBytes,
            ));
        }

        return [$accept, $maxBytes];
    }

    /**
     * What a `post` writes to the row it was drawn for.
     *
     * Specification 8.5 gives `archive` as its example of a `post` and never
     * says what archiving does, which is the one thing the action has to know:
     * `@action:archive` names a verb and the verb has no body. So `set` is
     * that body — the columns to write and the values to write — and the three
     * refusals here are what keep it from being a second, weaker form.
     *
     * A `post` that sets nothing and is not a delete is refused, because it
     * would pass every check, write nothing, flash success and leave the
     * person believing the row changed.
     *
     * A `link` with a `set` is refused for the reason a `link` with a
     * `confirm` is: it says the action changes data, and it does not.
     *
     * A value that is not a scalar is refused rather than coerced. The
     * temptation is an expression or a `{column}`, and both are forms wearing
     * an action's clothes — a thing that computes what it writes already
     * exists, with validation and a redraw on refusal, and it is the form
     * region.
     *
     * @param  array<string, mixed>                 $actionConfig
     * @return array<string, bool|float|int|string>
     */
    private function actionSet(
        string $where,
        ActionType $type,
        PageReference|ActionReference|RegionReference $to,
        array $actionConfig,
        bool $hasHandler = false,
    ): array {
        $raw = $actionConfig['set'] ?? null;
        $isDelete = $to instanceof ActionReference && $to->action === 'delete';

        if ($hasHandler && $raw !== null) {
            throw new PageException(
                "{$where} has both a 'handler' and a 'set'. Only one of them can run, and the file "
                . 'does not say which -- so neither is assumed. Keep the handler and write the columns '
                . "inside it, or drop the handler and keep the 'set'.",
            );
        }

        if ($hasHandler) {
            // The handler decides what is written, so there is nothing here
            // to require. It is still a `post`, still confirmed, still gated,
            // and still re-checks its `when`.
            return [];
        }

        if (!$type->isWrite()) {
            if ($raw !== null) {
                throw new PageException(\sprintf(
                    "%s is a '%s' with a 'set'. It writes nothing, so the columns it names would never be "
                    . "stored. Remove it, or make the action a 'post'.",
                    $where,
                    $type->value,
                ));
            }

            return [];
        }

        if ($raw === null || $raw === []) {
            if ($isDelete) {
                return [];
            }

            throw new PageException(
                "{$where} is a 'post' with no 'set', so it would change nothing and still report success. "
                . "Say what it writes — 'set' => ['state' => 'archived'] — or point it at "
                . "'@action:delete', which deletes the row instead.",
            );
        }

        if (!\is_array($raw)) {
            throw new PageException(\sprintf(
                "%s has 'set' => %s. It is a map of column key to the value to store, such as "
                . "['state' => 'archived'].",
                $where,
                get_debug_type($raw),
            ));
        }

        if ($isDelete) {
            throw new PageException(
                "{$where} points at '@action:delete' and also carries a 'set'. Deleting a row and writing "
                . 'to it are two different things and only one of them can happen. Keep the delete, or '
                . 'point the action at its own verb.',
            );
        }

        $set = [];

        foreach ($raw as $column => $value) {
            $columnKey = (string) $column;

            if (!\is_scalar($value)) {
                throw new PageException(\sprintf(
                    "%s sets '%s' to a %s. A 'set' stores literals — a string, a number, true or false — "
                    . 'never an expression and never a {column} placeholder: an action that computes what '
                    . 'it writes is a form, and a form region does that with validation and a redraw when '
                    . 'the value is refused.',
                    $where,
                    $columnKey,
                    get_debug_type($value),
                ));
            }

            $set[$columnKey] = $value;
        }

        return $set;
    }

    /**
     * Where the action goes: a reference, never a bare URL.
     *
     * Specification 8.5 gives the reason and it is a security one — a raw URL
     * cannot be permission-checked against a page, and would become the
     * obvious way to smuggle content into the admin frame. So the test is not
     * a pattern applied here: `Resolver` turns a well-formed reference into a
     * `PageReference` or an `ActionReference` the way it already turns
     * `@enum:` into an `EnumReference`, and anything that arrives as a plain
     * string is, by that fact alone, not a reference.
     *
     * **An `open` writes `target`, not `to`**, and the specification names it
     * that way on purpose: it does not go anywhere. The page stays where it
     * is and a region appears over it. `ActionType::destination()` owns which
     * key each type reads, so the two can never be crossed here.
     *
     * The reference is kept as the object, not flattened back to its text. A
     * row's URL is built from it by a renderer, and handing that renderer a
     * string to re-parse would be the second parsing mechanism this whole
     * arrangement exists to avoid.
     *
     * @param array<string, mixed> $actionConfig
     */
    private function actionTarget(
        string $where,
        ActionType $type,
        array $actionConfig,
        bool $rowless = false,
    ): PageReference|ActionReference|RegionReference {
        [$key, $expected] = $type->destination();
        $wrongKey = $key === 'to' ? 'target' : 'to';

        if (($actionConfig[$wrongKey] ?? null) !== null) {
            throw new PageException(\sprintf(
                "%s is a '%s' with a '%s'. A '%s' says where it goes with '%s'; '%s' belongs to an "
                . "'open', which does not go anywhere — the page stays where it is and a region appears "
                . 'over it.',
                $where,
                $type->value,
                $wrongKey,
                $type->value,
                $key,
                $wrongKey,
            ));
        }

        $to = $actionConfig[$key] ?? null;

        if ($to === null || $to === '') {
            throw new PageException(\sprintf(
                "%s has no '%s'. %s",
                $where,
                $key,
                $type->isOpen()
                    ? "An 'open' must say which region it shows, as a '@region:' reference — "
                        . "'target' => '@region:detail'."
                    : "An action must say where it goes, as a '@page:' or '@action:' reference — "
                        . "'to' => '@page:ads/{id}/edit'.",
            ));
        }

        $namespace = match (true) {
            $to instanceof PageReference => 'page',
            $to instanceof ActionReference => 'action',
            $to instanceof RegionReference => 'region',
            default => null,
        };

        if ($namespace === null) {
            throw new PageException(\sprintf(
                "%s has '%s' => %s, which is not a reference. An action points at a page, an action "
                . "handler or a region — '@page:ads/{id}/edit', '@action:archive', '@region:detail' — "
                . 'never at a bare URL: a raw URL cannot be permission-checked against a page, and would '
                . 'become the obvious way to smuggle content into the admin frame.',
                $where,
                $key,
                \is_string($to) ? "'{$to}'" : get_debug_type($to),
            ));
        }

        if ($namespace !== $expected) {
            throw new PageException(\sprintf(
                "%s is a '%s' pointing at '%s'. A 'link' navigates, so it points at '@page:...'; a 'post' "
                . "submits, so it points at '@action:...'; an 'open' shows a region, so its 'target' is "
                . "'@region:...'. The wrong one is a GET to something that only answers POST, or a POST "
                . 'to a page with no handler — invisible at load and a 404 when somebody clicks it.',
                $where,
                $type->value,
                (string) $to,
            ));
        }

        if ($to instanceof PageReference) {
            $this->assertPageExists($to, $where);
        }

        if ($to instanceof RegionReference && $to->page !== null) {
            // Only the cross-page form can be checked here. `@region:detail`
            // names a region of the page currently being built, whose own
            // regions are not finished yet -- the actions block is built
            // after them, but a column's inline action is built during them.
            $this->assertRegionExists($to, $where);
        }

        // A row placeholder is substituted while a region renders, from the
        // row being drawn. A control with no row behind it has nothing to
        // substitute from, and leaving it would carry the braces all the way
        // to `UrlGenerator::reference()`, which refuses them with a message
        // about a template -- three layers from the page file that wrote this.
        if ($rowless && $to instanceof PageReference && preg_match('/\{[^}]*}/', $to->suffix, $match) === 1) {
            throw new PageException(\sprintf(
                "%s points at '%s', and '%s' is a row placeholder: it is substituted per row, from the row "
                . 'being rendered, and this control is drawn once with no row behind it. Point it at the '
                . 'page itself, or put the action in a grid column where there is a row.',
                $where,
                (string) $to,
                $match[0],
            ));
        }

        return $to;
    }

    /**
     * The row conditions deciding whether the action is offered.
     *
     * A condition compares one column to one value, so a list of values is
     * refused rather than quietly compared against the array. Every column
     * must be one the grid declares: a condition on a column nobody selected
     * can never be true, so the action would silently never appear.
     *
     * @param  array<string, mixed>                 $actionConfig
     * @return array<string, bool|float|int|string>
     */
    private function actionConditions(
        string $where,
        array $actionConfig,
        ActionContext $context,
        bool $rowless = false,
    ): array {
        $when = $actionConfig['when'] ?? null;

        if (!\is_array($when) || $when === []) {
            return [];
        }

        if ($rowless) {
            throw new PageException(
                "{$where} has a 'when', which tests the values of a row, and this control is drawn once with "
                . 'no row behind it — so the condition could never be true and the control would never '
                . 'appear. Put the action in a grid column if it depends on a row.',
            );
        }

        if ($context->gridKey === null) {
            throw new PageException(
                "{$where} has a 'when', which tests the values of a row, and this page has no list region "
                . 'for a row to come from. Remove it, or give the page a list region.',
            );
        }

        $conditions = [];

        foreach ($when as $column => $value) {
            $column = (string) $column;

            if (!\is_scalar($value)) {
                throw new PageException(\sprintf(
                    "%s has 'when' => ['%s' => ...], which is a %s. A row condition compares one column to "
                    . 'one value.',
                    $where,
                    $column,
                    get_debug_type($value),
                ));
            }

            if (!\in_array($column, $context->gridColumns, true)) {
                $nearest = Schema::nearestOf($context->gridColumns, $column);
                $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

                throw new PageException(
                    "{$where} has a 'when' on '{$column}', which the region '{$context->gridKey}' does not "
                    . "declare, so the condition could never be true and the action would never appear.{$suffix}",
                );
            }

            $conditions[$column] = $value;
        }

        return $conditions;
    }

    /**
     * Which regions re-render once the action completes.
     *
     * A key naming no region would quietly refresh nothing, which looks
     * exactly like an action that did not run.
     *
     * @param  array<string, mixed> $actionConfig
     * @return list<string>
     */
    private function actionRefresh(string $where, array $actionConfig, ActionContext $context): array
    {
        $refresh = $actionConfig['refresh'] ?? null;

        if (!\is_array($refresh)) {
            return [];
        }

        $keys = [];

        foreach ($refresh as $entry) {
            if (!\is_string($entry) || $entry === '') {
                throw new PageException(\sprintf(
                    "%s has a 'refresh' entry that is a %s. It is a list of region keys.",
                    $where,
                    get_debug_type($entry),
                ));
            }

            if (!\in_array($entry, $context->regionKeys, true)) {
                $nearest = Schema::nearestOf($context->regionKeys, $entry);
                $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

                throw new PageException(
                    "{$where} refreshes the region '{$entry}', which this page does not have, so it would "
                    . "refresh nothing — which looks exactly like an action that did not run.{$suffix}",
                );
            }

            $keys[] = $entry;
        }

        return $keys;
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
            // Inheriting means inheriting the columns that *hold* something.
            // An `actions` column holds controls, not a value: it names no
            // column of the table, so a preview that inherited it would
            // SELECT `tools` and fail with "unknown column" — which is
            // exactly what `/p/ads/{id}` did from the moment the demo grew
            // an actions column, on the detail page and on the fragment
            // alike. Milestone 8 added the column type and this is the one
            // place that read a grid's columns as if they were all data.
            return $this->fieldsFromGrid(
                $pageName,
                $regionKey,
                $grid,
                static fn (RegionDefinition $g): array => array_values(array_filter(
                    $g->columns,
                    static fn (ColumnDefinition $column): bool => !$column->type->isActions(),
                )),
            );
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
            $column = $grid->columns[$fieldKey];

            if ($column->type->isActions()) {
                throw new PageException(\sprintf(
                    "Page '%s': region '%s' names field '%s', which is the list region's actions column. "
                    . 'It holds controls rather than a value, so there is nothing for a preview to show '
                    . "and nothing to select — the query would ask the database for a column called '%s'. "
                    . 'Put the actions on the page rather than in the preview.',
                    $pageName,
                    $regionKey,
                    $fieldKey,
                    $fieldKey,
                ));
            }

            return $column;
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

    /**
     * @param array<string, mixed> $regionConfig
     * @param array<string, mixed> $pageActions the page's own `actions` block, raw, for a column whose
     *                                          `items` names one of them
     */
    private function buildRegion(
        string $pageName,
        string $regionKey,
        array $regionConfig,
        Entity $entity,
        array $pageActions = [],
        ?ActionContext $context = null,
    ): RegionDefinition {
        $context ??= new ActionContext([]);

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
            $columns[$columnKey] = $this->buildColumn(
                $pageName,
                $columnKey,
                $columnConfig,
                $entity,
                $pageActions,
                $context,
            );

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
        $hasFormBlock = \array_key_exists('form', $regionConfig) && $regionConfig['form'] !== null;

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
        } elseif ($hasFormBlock) {
            // Everything else in this loader refuses configuration that
            // does nothing: a source paired with a collection, a filter on
            // a collection column, an undeclared sort target. A 'form'
            // block on a region whose type is not 'form' is read only
            // inside the branch above and would otherwise be dropped
            // exactly as silently.
            throw new PageException(\sprintf(
                "Page '%s': region '%s' has a 'form' block, but its type is '%s', not 'form'.",
                $pageName,
                $regionKey,
                $type->value,
            ));
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

        return new FormDefinition(
            $fields,
            $resetOnCopy,
            $this->handlerReference("Page '{$pageName}': region '{$regionKey}': form", $formConfig['handler'] ?? null),
        );
    }

    /** @param array<string, mixed> $fieldConfig */
    private function buildField(string $pageName, string $fieldKey, array $fieldConfig): FieldDefinition
    {
        // A form carries a few body keys that are not columns: the row's own
        // key and the return address. A field named one of them would render
        // a control colliding with the hidden input beside it, and whichever
        // the browser sent last would win — so a person could aim an update
        // at a different row by typing into a visible text box. The refusal
        // covers every leading underscore rather than only today's two
        // names, which is the rule FormFields itself states, so that adding
        // a third reserved key later cannot collide with a page already in
        // git.
        if (str_starts_with($fieldKey, '_')) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' may not begin with an underscore. Those names are reserved for the "
                    . "keys a form carries beside its fields: '%s'.",
                $pageName,
                $fieldKey,
                implode("', '", FormFields::reserved()),
            ));
        }

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

        // An explicit 'options' => [] is the same broken state as declaring
        // none at all: a control with nothing to choose from. Both are
        // refused the same way, by the same check below.
        $optionsRaw = \array_key_exists('options', $fieldConfig) ? $fieldConfig['options'] : null;
        $hasOptions = $optionsRaw !== null && $optionsRaw !== [];

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

        $options = $hasOptions ? $this->resolveOptions($optionsRaw, $pageName, $fieldKey, 'field') : [];

        $required = ($fieldConfig['required'] ?? false) === true;
        $readonly = ($fieldConfig['readonly'] ?? false) === true;
        $hidden = ($fieldConfig['hidden'] ?? false) === true;
        $default = \array_key_exists('default', $fieldConfig) ? $fieldConfig['default'] : null;

        // A default is a literal, a {{placeholder}}, or one of the tokens
        // DefaultValue knows how to expand -- @now and @uuid. Anything else
        // starting with '@' is refused here, at load, rather than left to
        // fail silently the first time a row is created: DefaultValue::for()
        // refuses it too, but only once a page is already being served.
        // isToken() is reused rather than a second literal list of tokens,
        // so the two cannot drift apart.
        if (\is_string($default) && str_starts_with($default, '@') && !DefaultValue::isToken($default)) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' has default '%s', which is not a known token. The tokens are '@now' "
                    . "and '@uuid'.",
                $pageName,
                $fieldKey,
                $default,
            ));
        }

        if ($required && $readonly) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' is both 'required' and 'readonly', which asks for a value the form "
                    . 'will never send.',
                $pageName,
                $fieldKey,
            ));
        }

        // Unlike readonly, hidden is not always the same dead end: a hidden
        // field's value comes from its default when the row is saved (rule
        // 7.6), so 'required' + 'hidden' with a default is how a workspace
        // scope is both always present and never editable. Without a
        // default there is nothing that could ever satisfy 'required'.
        if ($required && $hidden && $default === null) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' is both 'required' and 'hidden' but declares no 'default'. A hidden "
                    . 'field takes its value from its default when the row is saved, so nothing could ever '
                    . "satisfy 'required'.",
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

        // Compiled through FieldValidator::compile(), not a second
        // expression of its own: the validator anchors a pattern and sets
        // the D modifier, and a load-time check run against a different
        // expression from the one that actually runs is not a check.
        if ($pattern !== null && @preg_match(FieldValidator::compile($pattern), '') === false) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' has an invalid pattern '%s'.",
                $pageName,
                $fieldKey,
                $pattern,
            ));
        }

        // A pattern is run against a scalar value. A multiselect's value is
        // a list and a checkbox's is one bit that is never inspected, so on
        // either the key would sit in the page file doing nothing — and
        // silently dead configuration is what this loader exists to catch.
        if ($pattern !== null && ($type === FieldType::Multiselect || $type === FieldType::Checkbox)) {
            throw new PageException(\sprintf(
                "Page '%s': field '%s' is a '%s' and declares a 'pattern', which would never be applied. A "
                    . 'pattern constrains a single typed value, so it belongs on a text-like field.',
                $pageName,
                $fieldKey,
                $type->value,
            ));
        }

        [$accept, $maxBytes] = $this->fileLimits($pageName, $fieldKey, $type, $fieldConfig);

        return new FieldDefinition(
            key: $fieldKey,
            label: $label,
            type: $type,
            default: $default,
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
            accept: $accept,
            maxBytes: $maxBytes,
        );
    }

    /**
     * @param array<string, mixed> $columnConfig
     * @param array<string, mixed> $pageActions the page's own `actions` block, raw
     */
    private function buildColumn(
        string $pageName,
        string $columnKey,
        array $columnConfig,
        Entity $entity,
        array $pageActions = [],
        ?ActionContext $context = null,
    ): ColumnDefinition {
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

        $where = "Page '{$pageName}': column '{$columnKey}'";
        $align = \is_string($columnConfig['align'] ?? null) && $columnConfig['align'] !== ''
            ? $this->resolveAlign($pageName, $columnKey, $columnConfig['align'])
            : $type->defaultAlignment();
        $width = \is_string($columnConfig['width'] ?? null) ? $columnConfig['width'] : null;
        $class = \is_string($columnConfig['class'] ?? null) ? $columnConfig['class'] : '';

        // An actions column holds controls, not a value (spec 8.5), so it
        // leaves this method here: it has no source to reach, nothing to
        // format, and none of the keys below could do anything for it. Those
        // are refused by name first, the way every other dead pairing in this
        // loader is, rather than accepted and silently ignored.
        if ($type->isActions()) {
            $this->assertActionsColumnIsSound($where, $columnConfig);

            return new ColumnDefinition(
                filter: null,
                collection: null,
                key: $columnKey,
                label: $label,
                source: $columnKey,
                type: $type,
                display: $display,
                sortable: false,
                link: false,
                align: $align,
                width: $width,
                class: $class,
                actions: $this->buildActionItems(
                    $pageName,
                    "column '{$columnKey}'",
                    $columnConfig['items'] ?? null,
                    $pageActions,
                    $context ?? new ActionContext([]),
                    false,
                ),
            );
        }

        if (\array_key_exists('items', $columnConfig) && $columnConfig['items'] !== null) {
            throw new PageException(
                "{$where} has 'items', which lists the actions a column draws, and its type is "
                . "'{$type->value}'. A column either shows a value or draws controls — set "
                . "'type' => 'actions' on it, and name it for what it holds.",
            );
        }

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

        $enumOptions = \array_key_exists('options', $columnConfig)
            ? $this->resolveOptions($columnConfig['options'], $pageName, $columnKey, 'column')
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
     * What an `actions` column may not say about itself.
     *
     * It draws controls and names no database column, so it never reaches the
     * select list — which makes `sortable`, `searchable`, `filter` and `link`
     * exactly the silently dead configuration they are on a `collection`
     * column: a sort link that reorders nothing, a filter box that narrows
     * nothing, a cell wrapped in a link to the row it is already drawing links
     * for. `source` and `collection` are ways of getting a value, and there is
     * no value. Each is refused by name.
     *
     * `items` is required, because a column of controls with no controls is a
     * blank cell in every row and a header nobody can explain.
     *
     * @param array<string, mixed> $columnConfig
     */
    private function assertActionsColumnIsSound(string $where, array $columnConfig): void
    {
        $dead = [
            'source' => 'names a database column, and an actions column has no value to read',
            'collection' => 'fetches related values, and an actions column shows none',
            'sortable' => 'would order the rows by a column the query never selects',
            'searchable' => 'would search a column the query never selects',
            'filter' => 'would narrow the rows by a column the query never selects',
            'link' => 'wraps the cell in a link to the row, and this cell already draws its own',
        ];

        foreach ($dead as $key => $why) {
            $value = $columnConfig[$key] ?? null;

            if ($value === null || $value === false || $value === []) {
                continue;
            }

            throw new PageException(
                "{$where} is an actions column carrying '{$key}', which {$why} — so it would be accepted and "
                . 'do nothing. Remove it, or make this a column of its own that shows the value.',
            );
        }

        $items = $columnConfig['items'] ?? null;

        if (!\is_array($items) || $items === []) {
            throw new PageException(
                "{$where} is an actions column with no 'items', so every row would draw an empty cell under a "
                . "header nobody could explain. List the actions it draws: 'items' => ['edit', 'delete'].",
            );
        }
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
            $options = $this->resolveOptions($filterConfig['options'], $pageName, $columnKey, 'column');
        }

        return new FilterDefinition($type, $operator, $label, $options, $placeholder);
    }

    /**
     * Accepts an `@enum:` reference, or a literal map from value to either a
     * label string or `['label' => ..., 'color' => ...]`. Anything else is
     * refused by name: an entry that is neither of those is almost always a
     * typo, and dropping it would produce a filter quietly missing a choice.
     *
     * Shared by a column, its filter, and a form field — the shape is
     * identical for all three, so `$noun` is what makes each refusal name
     * the thing that actually carries the malformed `options`, rather than
     * every caller reading as if it were a column.
     *
     * @return array<string, EnumOption>
     */
    private function resolveOptions(mixed $raw, string $pageName, string $key, string $noun): array
    {
        if ($raw instanceof EnumReference) {
            try {
                return $this->enums->options($raw);
            } catch (ConfigException $e) {
                throw new PageException(
                    "Page '{$pageName}': {$noun} '{$key}': {$e->getMessage()}",
                    previous: $e,
                );
            }
        }

        if (!\is_array($raw)) {
            throw new PageException(
                "Page '{$pageName}': {$noun} '{$key}': options must be an @enum: reference or a map "
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
                "Page '{$pageName}': {$noun} '{$key}': the option '{$value}' is neither a label nor "
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
