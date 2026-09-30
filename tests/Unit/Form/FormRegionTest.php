<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\EnumOption;
use RockAdmin\Db\Entity;
use RockAdmin\Db\Query;
use RockAdmin\Db\Result;
use RockAdmin\Db\RowSource;
use RockAdmin\Db\Sql;
use RockAdmin\Form\FormFieldView;
use RockAdmin\Form\FormRegion;
use RockAdmin\Form\FormView;
use RockAdmin\Form\Submission;
use RockAdmin\Form\ValidationError;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageException;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\Page\RegionType;
use RockAdmin\Tests\Support\FakeRowSource;

/**
 * `FormRegion` turns a described form plus, when there is one, a stored row
 * into the `FormView` the form templates render. This tests that assembly
 * against a fake `RowSource`, never that any particular SQL ran.
 */
#[CoversClass(FormRegion::class)]
#[CoversClass(FormView::class)]
#[CoversClass(FormFieldView::class)]
final class FormRegionTest extends TestCase
{
    /** @var list<class-string> everything a view object may never carry */
    private const FORBIDDEN_IN_A_VIEW = [
        FieldDefinition::class,
        FormDefinition::class,
        RegionDefinition::class,
        PageDefinition::class,
        Query::class,
        Result::class,
        RowSource::class,
        Submission::class,
        Csrf::class,
        UrlGenerator::class,
    ];

    /** @param array<string, EnumOption> $options */
    private function field(
        string $key,
        FieldType $type = FieldType::Text,
        mixed $default = null,
        bool $readonly = false,
        bool $hidden = false,
        array $options = [],
    ): FieldDefinition {
        return new FieldDefinition(
            key: $key,
            label: ucfirst(str_replace('_', ' ', $key)),
            type: $type,
            default: $default,
            required: false,
            readonly: $readonly,
            hidden: $hidden,
            help: '',
            placeholder: '',
            options: $options,
        );
    }

    /**
     * @param array<string, FieldDefinition> $fields
     * @param list<string>                   $resetOnCopy
     */
    private function region(array $fields, array $resetOnCopy = [], string $key = 'form'): RegionDefinition
    {
        return new RegionDefinition(
            key: $key,
            type: RegionType::Form,
            perPage: 25,
            columns: [],
            sort: [],
            searchable: [],
            form: new FormDefinition($fields, $resetOnCopy),
        );
    }

    private function page(RegionDefinition ...$regions): PageDefinition
    {
        $keyed = [];

        foreach ($regions as $region) {
            $keyed[$region->key] = $region;
        }

        return new PageDefinition('ads', 'Ads', 'default', 'Ads', new Entity('ra_test_ads', 'id'), [], $keyed);
    }

    /** @param list<array<string, mixed>> $rows */
    private function fetchResult(array $rows): Result
    {
        return new Result($rows, null, [new Sql('SELECT 1')]);
    }

    private function formRegion(RowSource $rows): FormRegion
    {
        return new FormRegion($rows, new UrlGenerator('/admin'), new Csrf(new ArraySessionStore()));
    }

    private function fieldView(FormView $view, string $key): FormFieldView
    {
        foreach ($view->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        self::fail("The form has no field '{$key}'.");
    }

    /** @return list<string> */
    private function keysOf(FormView $view): array
    {
        return array_map(static fn (FormFieldView $field): string => $field->key, $view->fields);
    }

    public function testACreateFormStartsEveryFieldAtItsDefault(): void
    {
        $region = $this->region([
            'title' => $this->field('title', default: 'Untitled'),
            'price' => $this->field('price', FieldType::Number, default: 0),
            'note' => $this->field('note'),
        ]);

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))
            ->create($this->page($region), $region);

        $this->assertTrue($view->isCreate);
        $this->assertSame(['title', 'price', 'note'], $this->keysOf($view));
        $this->assertSame('Untitled', $this->fieldView($view, 'title')->value);
        $this->assertSame('0', $this->fieldView($view, 'price')->value);
        $this->assertSame('', $this->fieldView($view, 'note')->value);
    }

    public function testAnEditFormStartsEveryFieldAtTheRowsValue(): void
    {
        $region = $this->region([
            'title' => $this->field('title', default: 'Untitled'),
            'price' => $this->field('price', FieldType::Number, default: 0),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'price' => 12000]])]);

        $view = $this->formRegion($rows)->edit($this->page($region), $region, '7');

        $this->assertNotNull($view);
        $this->assertFalse($view->isCreate);
        $this->assertSame('7', $view->id);
        $this->assertSame('Bike', $this->fieldView($view, 'title')->value);
        $this->assertSame('12000', $this->fieldView($view, 'price')->value);
    }

    public function testAnEditFormForAMissingRowIsNull(): void
    {
        $region = $this->region(['title' => $this->field('title')]);

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))
            ->edit($this->page($region), $region, '999999');

        $this->assertNull($view);
    }

    public function testACopyFormCarriesTheRowExceptTheFieldsResetNames(): void
    {
        $region = $this->region(
            [
                'title' => $this->field('title'),
                'slug' => $this->field('slug', default: 'new-slug'),
            ],
            ['slug'],
        );
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'slug' => 'bike']])]);

        $view = $this->formRegion($rows)->copy($this->page($region), $region, '7');

        $this->assertNotNull($view);
        $this->assertTrue($view->isCreate, 'a copy saves a new row');
        $this->assertNull($view->id, 'a copy is not bound to the row it came from');
        $this->assertSame('Bike', $this->fieldView($view, 'title')->value);
        $this->assertSame('new-slug', $this->fieldView($view, 'slug')->value);
    }

    public function testARejectedSubmissionKeepsWhatWasTyped(): void
    {
        $fields = [
            'title' => $this->field('title', default: 'Untitled'),
            'price' => $this->field('price', FieldType::Number, default: 0),
        ];
        $region = $this->region($fields);
        $submission = Submission::fromBody(
            ['title' => 'Typed by hand', 'price' => 'not a number'],
            new FormDefinition($fields),
        );

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))
            ->reject($this->page($region), $region, $submission, [], null);

        $this->assertSame('Typed by hand', $this->fieldView($view, 'title')->value);
        $this->assertSame('not a number', $this->fieldView($view, 'price')->value);
    }

    public function testARejectedSubmissionAttachesEachErrorToItsField(): void
    {
        $fields = [
            'title' => $this->field('title'),
            'price' => $this->field('price', FieldType::Number),
        ];
        $region = $this->region($fields);
        $submission = Submission::fromBody(['title' => '', 'price' => 'x'], new FormDefinition($fields));
        $errors = [
            new ValidationError('title', 'Title is required.'),
            new ValidationError('price', 'Price must be a number.'),
            new ValidationError('price', 'Price must be at least 1.'),
        ];

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))
            ->reject($this->page($region), $region, $submission, $errors, null);

        $this->assertSame('Title is required.', $this->fieldView($view, 'title')->error());
        $this->assertSame(
            ['Price must be a number.', 'Price must be at least 1.'],
            $this->fieldView($view, 'price')->errors,
            'every failure reaches the field it belongs to, not just the first',
        );
        $this->assertSame([], $view->errors, 'an error naming a field is not also a form-level one');
    }

    public function testARejectedCreateAndAFreshCreateDrawTheSameControls(): void
    {
        $fields = [
            'title' => $this->field('title', default: 'Untitled'),
            'status' => $this->field('status', FieldType::Select, default: 'draft', options: [
                'draft' => new EnumOption('draft', 'Draft'),
                'live' => new EnumOption('live', 'Live'),
            ]),
            'owner' => $this->field('owner', readonly: true, default: 'system'),
            'note' => $this->field('note', FieldType::Textarea),
        ];
        $regionDefinition = $this->region($fields);
        $page = $this->page($regionDefinition);
        $submission = Submission::fromBody(
            ['title' => 'Typed', 'status' => 'live', 'note' => 'Hello'],
            new FormDefinition($fields),
        );
        $region = $this->formRegion(new FakeRowSource([$this->fetchResult([])]));

        $fresh = $region->create($page, $regionDefinition);
        $rejected = $region->reject(
            $page,
            $regionDefinition,
            $submission,
            [new ValidationError('title', 'Too short.')],
            null,
        );

        $this->assertSame($this->keysOf($fresh), $this->keysOf($rejected));

        foreach ($fresh->fields as $index => $field) {
            $redrawn = $rejected->fields[$index];

            $this->assertSame($field->key, $redrawn->key);
            $this->assertSame($field->label, $redrawn->label);
            $this->assertSame($field->type, $redrawn->type);
            $this->assertEquals($field->options, $redrawn->options);
            $this->assertSame($field->classes(), $redrawn->classes());
            $this->assertSame($field->readonly, $redrawn->readonly);
            $this->assertSame($field->required, $redrawn->required);
        }

        $this->assertSame($fresh->action, $rejected->action);
        $this->assertSame($fresh->isCreate, $rejected->isCreate);
    }

    public function testTheFormCarriesACsrfToken(): void
    {
        $csrf = new Csrf(new ArraySessionStore());
        $region = new FormRegion(
            new FakeRowSource([$this->fetchResult([])]),
            new UrlGenerator('/admin'),
            $csrf,
        );
        $definition = $this->region(['title' => $this->field('title')]);

        $view = $region->create($this->page($definition), $definition);

        $this->assertSame($csrf->token(), $view->token);
    }

    public function testTheActionUrlIsThePagesActionRoute(): void
    {
        $definition = $this->region(['title' => $this->field('title')]);
        $page = $this->page($definition);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike']])]);
        $region = $this->formRegion($rows);

        $this->assertSame('/admin/a/ads/create', $region->create($page, $definition)->action);

        $edit = $region->edit($page, $definition, '7');
        $this->assertNotNull($edit);
        $this->assertSame('/admin/a/ads/update', $edit->action);
    }

    public function testTheFormGoesBackWhereTheCallerSaysAndToThePageIndexOtherwise(): void
    {
        // Spec 8.11's `_ret`: a person who opened the form from page three of
        // a filtered grid must land back on page three of that filtered grid.
        // FormRegion neither validates nor invents the address — Task 8's
        // ReturnAddress decides what is acceptable — it only carries what it
        // was handed, and falls back to the page's own index when handed
        // nothing rather than to whatever was in the request.
        $definition = $this->region(['title' => $this->field('title')]);
        $page = $this->page($definition);
        $region = $this->formRegion(new FakeRowSource([$this->fetchResult([])]));

        $carried = $region->create($page, $definition, '/admin/p/ads?grid%5Bpage%5D=3');
        $this->assertSame('/admin/p/ads?grid%5Bpage%5D=3', $carried->returnTo);

        $fallback = $region->create($page, $definition);
        $this->assertSame('/admin/p/ads', $fallback->returnTo, 'the page index is the fallback return address');
    }

    public function testASecondFormRegionOnThePageIsTheOneRenderedWhenItIsTheOneNamed(): void
    {
        // The caller names the region, the way it does for a preview. A class
        // that called firstFormRegion() itself could only ever draw the first
        // form on a page, and milestone 8's `@region:` addressing would have
        // no way to ask for the other one.
        $first = $this->region(['title' => $this->field('title')], key: 'form');
        $second = $this->region(['note' => $this->field('note')], key: 'quick-form');
        $page = $this->page($first, $second);
        $region = $this->formRegion(new FakeRowSource([$this->fetchResult([])]));

        $this->assertSame(['note'], $this->keysOf($region->create($page, $second)));
        $this->assertSame(['title'], $this->keysOf($region->create($page, $first)));
    }

    public function testARegionThatIsNotAFormIsRefusedNamingIt(): void
    {
        $page = new PageDefinition(
            'ads',
            'Ads',
            'default',
            'Ads',
            new Entity('ra_test_ads', 'id'),
            [],
            [],
        );
        $grid = new RegionDefinition('grid', RegionType::List, 25, [], [], []);
        $region = $this->formRegion(new FakeRowSource([$this->fetchResult([])]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Region 'grid' of page 'ads' is not a form.");

        $region->create($page, $grid);
    }

    public function testAReadonlyFieldIsRenderedDisabled(): void
    {
        $definition = $this->region([
            'title' => $this->field('title'),
            'created_at' => $this->field('created_at', readonly: true),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([
            ['id' => 7, 'title' => 'Bike', 'created_at' => '2026-09-24 10:00:00'],
        ])]);

        $view = $this->formRegion($rows)->edit($this->page($definition), $definition, '7');

        $this->assertNotNull($view);
        $this->assertFalse($this->fieldView($view, 'title')->readonly);
        $this->assertTrue($this->fieldView($view, 'created_at')->readonly);
        $this->assertSame('2026-09-24 10:00:00', $this->fieldView($view, 'created_at')->value);
    }

    public function testAHiddenFieldFromARowTravelsButOneFromADefaultDoesNot(): void
    {
        $definition = $this->region([
            'title' => $this->field('title'),
            'site_id' => $this->field('site_id', hidden: true, default: 3),
        ]);
        $page = $this->page($definition);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'site_id' => 9]])]);
        $region = $this->formRegion($rows);

        $created = $region->create($page, $definition);
        $this->assertSame(['title'], $this->keysOf($created), 'a default is reapplied on save, so it stays off the page');

        $edited = $region->edit($page, $definition, '7');
        $this->assertNotNull($edited);
        $this->assertSame(['title', 'site_id'], $this->keysOf($edited));

        $travelling = $this->fieldView($edited, 'site_id');
        $this->assertSame(FieldType::Hidden, $travelling->type, 'a hidden field has no control of its own');
        $this->assertSame('9', $travelling->value);
    }

    public function testNoViewObjectCarriesAFieldDefinition(): void
    {
        $definition = $this->region([
            'title' => $this->field('title'),
            'status' => $this->field('status', FieldType::Select, options: [
                'draft' => new EnumOption('draft', 'Draft'),
            ]),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'status' => 'draft']])]);

        $view = $this->formRegion($rows)->edit($this->page($definition), $definition, '7');

        $this->assertNotNull($view);
        $this->assertNothingForbiddenReachable($view);
    }

    /**
     * Rule 4 of this project: a template receives a prepared view object and
     * never the configuration or the database. Checking only for a
     * `FieldDefinition` would pass a view that handed templates the form's
     * own definition, the page, the query or the row source — each a route to
     * exactly what the rule forbids, and each one a future field could
     * introduce without anyone noticing.
     *
     * @param array<int, true> $seen object ids already visited, guarding against cycles
     */
    private function assertNothingForbiddenReachable(object $object, array $seen = []): void
    {
        $id = spl_object_id($object);

        if (isset($seen[$id])) {
            return;
        }

        $seen[$id] = true;

        foreach (self::FORBIDDEN_IN_A_VIEW as $forbidden) {
            $this->assertNotInstanceOf($forbidden, $object);
        }

        foreach (get_object_vars($object) as $value) {
            if (\is_object($value)) {
                $this->assertNothingForbiddenReachable($value, $seen);
            } elseif (\is_array($value)) {
                foreach ($value as $item) {
                    if (\is_object($item)) {
                        $this->assertNothingForbiddenReachable($item, $seen);
                    }
                }
            }
        }
    }

    public function testOneStatementFetchesTheRowForAnEditForm(): void
    {
        $definition = $this->region([
            'title' => $this->field('title'),
            'price' => $this->field('price', FieldType::Number),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'price' => 1]])]);

        $this->formRegion($rows)->edit($this->page($definition), $definition, '7');

        $this->assertSame(1, $rows->calls, 'one RowSource::fetch() call for one row');

        $query = $rows->queries[0];
        $this->assertNotNull($query->page);
        $this->assertSame(1, $query->page->limit);
        $this->assertCount(1, $query->filters);
        $this->assertSame('id', $query->filters[0]->column);
        $this->assertSame('7', $query->filters[0]->value);
        $this->assertSame(['id', 'title', 'price'], array_keys($query->columns));
    }
}
