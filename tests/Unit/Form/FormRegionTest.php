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
    private function page(array $fields, array $resetOnCopy = []): PageDefinition
    {
        $region = new RegionDefinition(
            key: 'form',
            type: RegionType::Form,
            perPage: 25,
            columns: [],
            sort: [],
            searchable: [],
            form: new FormDefinition($fields, $resetOnCopy),
        );

        return new PageDefinition(
            'ads',
            'Ads',
            'default',
            'Ads',
            new Entity('ra_test_ads', 'id'),
            [],
            ['form' => $region],
        );
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
        $page = $this->page([
            'title' => $this->field('title', default: 'Untitled'),
            'price' => $this->field('price', FieldType::Number, default: 0),
            'note' => $this->field('note'),
        ]);

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))->create($page);

        $this->assertTrue($view->isCreate);
        $this->assertSame(['title', 'price', 'note'], $this->keysOf($view));
        $this->assertSame('Untitled', $this->fieldView($view, 'title')->value);
        $this->assertSame('0', $this->fieldView($view, 'price')->value);
        $this->assertSame('', $this->fieldView($view, 'note')->value);
    }

    public function testAnEditFormStartsEveryFieldAtTheRowsValue(): void
    {
        $page = $this->page([
            'title' => $this->field('title', default: 'Untitled'),
            'price' => $this->field('price', FieldType::Number, default: 0),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'price' => 12000]])]);

        $view = $this->formRegion($rows)->edit($page, '7');

        $this->assertNotNull($view);
        $this->assertFalse($view->isCreate);
        $this->assertSame('7', $view->id);
        $this->assertSame('Bike', $this->fieldView($view, 'title')->value);
        $this->assertSame('12000', $this->fieldView($view, 'price')->value);
    }

    public function testAnEditFormForAMissingRowIsNull(): void
    {
        $page = $this->page(['title' => $this->field('title')]);

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))->edit($page, '999999');

        $this->assertNull($view);
    }

    public function testACopyFormCarriesTheRowExceptTheFieldsResetNames(): void
    {
        $page = $this->page(
            [
                'title' => $this->field('title'),
                'slug' => $this->field('slug', default: 'new-slug'),
            ],
            ['slug'],
        );
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'slug' => 'bike']])]);

        $view = $this->formRegion($rows)->copy($page, '7');

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
        $page = $this->page($fields);
        $submission = Submission::fromBody(
            ['title' => 'Typed by hand', 'price' => 'not a number'],
            new FormDefinition($fields),
        );

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))
            ->reject($page, $submission, [], null);

        $this->assertSame('Typed by hand', $this->fieldView($view, 'title')->value);
        $this->assertSame('not a number', $this->fieldView($view, 'price')->value);
    }

    public function testARejectedSubmissionAttachesEachErrorToItsField(): void
    {
        $fields = [
            'title' => $this->field('title'),
            'price' => $this->field('price', FieldType::Number),
        ];
        $page = $this->page($fields);
        $submission = Submission::fromBody(['title' => '', 'price' => 'x'], new FormDefinition($fields));
        $errors = [
            new ValidationError('title', 'Title is required.'),
            new ValidationError('price', 'Price must be a number.'),
            new ValidationError('price', 'Price must be at least 1.'),
        ];

        $view = $this->formRegion(new FakeRowSource([$this->fetchResult([])]))
            ->reject($page, $submission, $errors, null);

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
        $page = $this->page($fields);
        $submission = Submission::fromBody(
            ['title' => 'Typed', 'status' => 'live', 'note' => 'Hello'],
            new FormDefinition($fields),
        );
        $region = $this->formRegion(new FakeRowSource([$this->fetchResult([])]));

        $fresh = $region->create($page);
        $rejected = $region->reject($page, $submission, [new ValidationError('title', 'Too short.')], null);

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
        $session = new ArraySessionStore();
        $csrf = new Csrf($session);
        $region = new FormRegion(
            new FakeRowSource([$this->fetchResult([])]),
            new UrlGenerator('/admin'),
            $csrf,
        );

        $view = $region->create($this->page(['title' => $this->field('title')]));

        $this->assertSame($csrf->token(), $view->token);
    }

    public function testTheActionUrlIsThePagesActionRoute(): void
    {
        $page = $this->page(['title' => $this->field('title')]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike']])]);
        $region = $this->formRegion($rows);

        $this->assertSame('/admin/a/ads/create', $region->create($page)->action);

        $edit = $region->edit($page, '7');
        $this->assertNotNull($edit);
        $this->assertSame('/admin/a/ads/update', $edit->action);
        $this->assertSame('/admin/p/ads', $edit->returnTo, 'the page index is the fallback return address');
    }

    public function testAReadonlyFieldIsRenderedDisabled(): void
    {
        $page = $this->page([
            'title' => $this->field('title'),
            'created_at' => $this->field('created_at', readonly: true),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([
            ['id' => 7, 'title' => 'Bike', 'created_at' => '2026-09-24 10:00:00'],
        ])]);

        $view = $this->formRegion($rows)->edit($page, '7');

        $this->assertNotNull($view);
        $this->assertFalse($this->fieldView($view, 'title')->readonly);
        $this->assertTrue($this->fieldView($view, 'created_at')->readonly);
        $this->assertSame('2026-09-24 10:00:00', $this->fieldView($view, 'created_at')->value);
    }

    public function testAHiddenFieldFromARowTravelsButOneFromADefaultDoesNot(): void
    {
        $page = $this->page([
            'title' => $this->field('title'),
            'site_id' => $this->field('site_id', hidden: true, default: 3),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'site_id' => 9]])]);
        $region = $this->formRegion($rows);

        $created = $region->create($page);
        $this->assertSame(['title'], $this->keysOf($created), 'a default is reapplied on save, so it stays off the page');

        $edited = $region->edit($page, '7');
        $this->assertNotNull($edited);
        $this->assertSame(['title', 'site_id'], $this->keysOf($edited));

        $travelling = $this->fieldView($edited, 'site_id');
        $this->assertSame(FieldType::Hidden, $travelling->type, 'a hidden field has no control of its own');
        $this->assertSame('9', $travelling->value);
    }

    public function testNoViewObjectCarriesAFieldDefinition(): void
    {
        $page = $this->page([
            'title' => $this->field('title'),
            'status' => $this->field('status', FieldType::Select, options: [
                'draft' => new EnumOption('draft', 'Draft'),
            ]),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'status' => 'draft']])]);

        $view = $this->formRegion($rows)->edit($page, '7');

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
        $page = $this->page([
            'title' => $this->field('title'),
            'price' => $this->field('price', FieldType::Number),
        ]);
        $rows = new FakeRowSource([$this->fetchResult([['id' => 7, 'title' => 'Bike', 'price' => 1]])]);

        $this->formRegion($rows)->edit($page, '7');

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
