<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Form;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\EnumOption;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormFieldView;
use RockAdmin\Form\FormView;
use RockAdmin\Form\Submission;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;

/**
 * The form region's templates, rendered as they ship over hand-built views —
 * the same style as `ListTemplatesTest` and `PreviewTemplatesTest`.
 *
 * Everything here is asserted against a parsed document rather than against
 * a substring of the markup. A substring assertion passes on markup that is
 * structurally broken, and this project has shipped exactly that: milestone
 * 4's flash toast was invisible for a whole milestone because its test
 * asserted the classes and the text, both true of markup Bootstrap was
 * hiding. So: a label's `for` is followed to the element it names, an
 * `aria-describedby` is followed to the element it names, and a `<script>`
 * in a value is looked for as an element, not as a substring.
 */
#[CoversNothing]
final class FormTemplatesTest extends TestCase
{
    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));
    }

    /**
     * @param array<string, EnumOption> $options
     * @param list<string>              $errors
     * @param bool|string|list<string>  $value
     */
    private function field(
        string $key = 'title',
        string $label = 'Title',
        FieldType $type = FieldType::Text,
        bool|string|array $value = 'Hello',
        array $options = [],
        string $help = '',
        string $placeholder = '',
        bool $required = false,
        bool $readonly = false,
        array $errors = [],
        ?int $min = null,
        ?int $max = null,
        ?string $step = null,
        ?int $rows = null,
        ?string $pattern = null,
    ): FormFieldView {
        return new FormFieldView(
            key: $key,
            label: $label,
            type: $type,
            value: $value,
            options: $options,
            help: $help,
            placeholder: $placeholder,
            required: $required,
            readonly: $readonly,
            errors: $errors,
            min: $min,
            max: $max,
            step: $step,
            rows: $rows,
            pattern: $pattern,
        );
    }

    /**
     * @param list<FormFieldView> $fields
     * @param list<string>        $errors
     */
    private function form(
        array $fields = [],
        array $errors = [],
        bool $isCreate = true,
        ?string $id = null,
        string $returnTo = '/admin/p/ads',
    ): FormView {
        return new FormView(
            page: 'ads',
            title: $isCreate ? 'New Ad' : 'Edit Ad',
            action: '/admin/a/ads/' . ($isCreate ? 'create' : 'update'),
            token: 'tok-123',
            returnTo: $returnTo,
            isCreate: $isCreate,
            fields: $fields === [] ? [$this->field()] : $fields,
            errors: $errors,
            id: $id,
        );
    }

    /** @return array<string, EnumOption> */
    private function options(string ...$values): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = new EnumOption($value, ucfirst($value));
        }

        return $options;
    }

    /**
     * `loadHTML()` reports every HTML5 element and attribute libxml's HTML4
     * parser does not know as an error. Letting those reach the default
     * handler would print a warning per render and make the suite noisy, so
     * they are captured into libxml's internal buffer and cleared here
     * deliberately — this test is about structure, and a parser complaining
     * about `<input required>` says nothing about that.
     */
    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html lang="en"><head><title>t</title></head><body>' . $html . '</body></html>',
            LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /** @return list<DOMElement> */
    private function all(DOMXPath $xpath, string $query): array
    {
        $found = $xpath->query($query);
        $elements = [];

        if ($found instanceof DOMNodeList) {
            foreach ($found as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }
        }

        return $elements;
    }

    private function one(DOMXPath $xpath, string $query): DOMElement
    {
        $elements = $this->all($xpath, $query);

        $this->assertCount(1, $elements, "expected exactly one element for {$query}");

        return $elements[0];
    }

    /** A class attribute is a token list, so `contains()` alone would match a prefix. */
    private function hasClass(string $class): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    // --- region.php: the form itself ---

    public function testTheFormIsAnOrdinaryPostToItsActionRoute(): void
    {
        $html = $this->renderer()->render('region/form/region', $this->form());
        $xpath = $this->dom($html);

        $form = $this->one($xpath, '//form');

        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame('/admin/a/ads/create', $form->getAttribute('action'));
    }

    public function testTheFormCarriesItsCsrfTokenUnderTheNameTheHandlerReadsBack(): void
    {
        $html = $this->renderer()->render('region/form/region', $this->form());
        $xpath = $this->dom($html);

        $input = $this->one($xpath, '//input[@name="' . Csrf::FIELD . '"]');

        $this->assertSame('hidden', $input->getAttribute('type'));
        $this->assertSame('tok-123', $input->getAttribute('value'));
    }

    public function testTheFormCarriesItsReturnAddressUnderTheNameTheHandlerReadsBack(): void
    {
        $html = $this->renderer()->render('region/form/region', $this->form(returnTo: '/admin/p/ads?page=2'));
        $xpath = $this->dom($html);

        $input = $this->one($xpath, '//input[@name="' . FormFields::RETURN_TO . '"]');

        $this->assertSame('hidden', $input->getAttribute('type'));
        $this->assertSame('/admin/p/ads?page=2', $input->getAttribute('value'));
    }

    public function testAnEditFormCarriesTheRowsKeyInTheBodyBecauseTheRouteDoesNotNameIt(): void
    {
        $html = $this->renderer()->render('region/form/region', $this->form(isCreate: false, id: '42'));
        $xpath = $this->dom($html);

        $input = $this->one($xpath, '//input[@name="' . FormFields::ID . '"]');

        $this->assertSame('hidden', $input->getAttribute('type'));
        $this->assertSame('42', $input->getAttribute('value'));
    }

    public function testACreateFormCarriesNoRowKeyAtAll(): void
    {
        $html = $this->renderer()->render('region/form/region', $this->form());
        $xpath = $this->dom($html);

        $this->assertSame([], $this->all($xpath, '//input[@name="' . FormFields::ID . '"]'));
    }

    public function testEveryLabelPointsAtAnElementThatIsActuallyInTheDocument(): void
    {
        // Milestone 6 shipped a range filter whose label pointed at nothing.
        // Every control type is in this one form, so the check covers all of
        // them at once and a new type cannot be added without meeting it.
        $html = $this->renderer()->render('region/form/region', $this->form(fields: $this->everyType()));
        $xpath = $this->dom($html);

        $labels = $this->all($xpath, '//label[@for]');

        $this->assertGreaterThanOrEqual(10, \count($labels));

        foreach ($labels as $label) {
            $for = $label->getAttribute('for');

            $this->assertCount(
                1,
                $this->all($xpath, '//*[@id="' . $for . '"]'),
                "the label 'for={$for}' names no element in the document",
            );
        }
    }

    // --- field.php and the eleven control templates ---

    /** @return array<string, array{FieldType}> */
    public static function everyFieldType(): array
    {
        $cases = [];

        foreach (FieldType::cases() as $type) {
            $cases[$type->value] = [$type];
        }

        return $cases;
    }

    #[DataProvider('everyFieldType')]
    public function testEachTypeIsDrawnByTheTemplateNamedAfterIt(FieldType $type): void
    {
        $field = $this->field(
            type: $type,
            value: match (true) {
                $type->isMultiple() => ['a'],
                $type === FieldType::Checkbox => true,
                default => 'a',
            },
            options: $type->takesOptions() ? $this->options('a', 'b') : [],
        );

        $html = $this->renderer()->render('region/form/field', $field);
        $xpath = $this->dom($html);

        // Each control template's own class is its filename, so a project
        // restyling every date input knows which file to copy.
        $this->assertCount(
            1,
            $this->all($xpath, '//*[' . $this->hasClass('ra-form-control-' . $type->value) . ']'),
            "field/{$type->value}.php did not draw the control",
        );
    }

    public function testATextFieldCarriesItsValuePlaceholderAndLengthBounds(): void
    {
        $field = $this->field(value: 'Bike', placeholder: 'Name it', min: 2, max: 40, pattern: '[A-Za-z ]+');

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="text"]');

        $this->assertSame('Bike', $input->getAttribute('value'));
        $this->assertSame('Name it', $input->getAttribute('placeholder'));
        $this->assertSame('2', $input->getAttribute('minlength'));
        $this->assertSame('40', $input->getAttribute('maxlength'));
        $this->assertSame('[A-Za-z ]+', $input->getAttribute('pattern'));
    }

    public function testANumberFieldCarriesItsBoundsAndItsStep(): void
    {
        $field = $this->field(
            key: 'price',
            label: 'Price',
            type: FieldType::Number,
            value: '10',
            min: 0,
            max: 99,
            step: '0.01',
        );

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="number"]');

        $this->assertSame('0', $input->getAttribute('min'));
        $this->assertSame('99', $input->getAttribute('max'));
        $this->assertSame('0.01', $input->getAttribute('step'));
    }

    public function testATextareaHoldsItsValueAsTextAndItsHeightAsRows(): void
    {
        $field = $this->field(key: 'body', label: 'Body', type: FieldType::Textarea, value: "line\nline", rows: 7);

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $textarea = $this->one($xpath, '//textarea');

        $this->assertSame('7', $textarea->getAttribute('rows'));
        $this->assertSame("line\nline", $textarea->textContent);
    }

    public function testASelectOffersEveryOptionAndMarksTheStoredOne(): void
    {
        $field = $this->field(
            key: 'status',
            label: 'Status',
            type: FieldType::Select,
            value: 'sold',
            options: $this->options('draft', 'live', 'sold'),
        );

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $select = $this->one($xpath, '//select');

        $this->assertFalse($select->hasAttribute('multiple'));
        $this->assertCount(3, $this->all($xpath, '//option[@value!=""]'));

        $selected = $this->all($xpath, '//option[@selected]');
        $this->assertCount(1, $selected);
        $this->assertSame('sold', $selected[0]->getAttribute('value'));
    }

    public function testAMultiselectPostsAnArrayAndMarksBothSelectedOptions(): void
    {
        $field = $this->field(
            key: 'tags',
            label: 'Tags',
            type: FieldType::Multiselect,
            value: ['sale', 'used'],
            options: $this->options('sale', 'used', 'new'),
        );

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $select = $this->one($xpath, '//select');

        $this->assertSame('tags[]', $select->getAttribute('name'));
        $this->assertTrue($select->hasAttribute('multiple'));

        $selected = array_map(
            static fn (DOMElement $option): string => $option->getAttribute('value'),
            $this->all($xpath, '//option[@selected]'),
        );

        $this->assertSame(['sale', 'used'], $selected);
    }

    public function testARadioDrawsOneInputPerOptionEachWithItsOwnLabel(): void
    {
        $field = $this->field(
            key: 'state',
            label: 'State',
            type: FieldType::Radio,
            value: 'used',
            options: $this->options('new', 'used'),
        );

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));

        $inputs = $this->all($xpath, '//input[@type="radio"]');
        $this->assertCount(2, $inputs);

        foreach ($inputs as $input) {
            $this->assertSame('state', $input->getAttribute('name'));
            $this->assertCount(
                1,
                $this->all($xpath, '//label[@for="' . $input->getAttribute('id') . '"]'),
                'each radio has a label of its own',
            );
        }

        $this->assertCount(1, $this->all($xpath, '//input[@type="radio"][@checked]'));
    }

    public function testACheckedCheckboxIsChecked(): void
    {
        $field = $this->field(key: 'live', label: 'Live', type: FieldType::Checkbox, value: true);

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="checkbox"]');

        $this->assertTrue($input->hasAttribute('checked'));
    }

    public function testAnUncheckedCheckboxIsNotCheckedAndSendsNothing(): void
    {
        $field = $this->field(key: 'live', label: 'Live', type: FieldType::Checkbox, value: false);

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));

        $this->assertSame([], $this->all($xpath, '//input[@checked]'));
        // An unchecked box sends nothing at all; a companion hidden input
        // would make "absent means false" a lie, and FieldValidator reads
        // absence as false on purpose.
        $this->assertSame([], $this->all($xpath, '//input[@type="hidden"]'));
    }

    public function testADateFieldUsesTheNativeDateControl(): void
    {
        $field = $this->field(key: 'day', label: 'Day', type: FieldType::Date, value: '2026-09-24');

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="date"]');

        $this->assertSame('2026-09-24', $input->getAttribute('value'));
    }

    public function testADatetimeRendersAValueTheValidatorWillAcceptBackAgain(): void
    {
        // A `datetime-local` control wants the ISO `T` separator; a row hands
        // back a space. Rendering the space form would draw an empty control
        // in the browser, and rendering anything outside
        // FieldValidator::DATETIME_FORMATS would break the round trip
        // silently — a value that was drawn correctly failing validation on
        // the way back.
        $field = $this->field(key: 'at', label: 'At', type: FieldType::Datetime, value: '2026-09-24 10:30:00');

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="datetime-local"]');

        $value = $input->getAttribute('value');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value);

        $this->assertNotFalse($parsed, "'{$value}' is not the T form a datetime-local control reads");
        $this->assertSame($value, $parsed->format('Y-m-d\TH:i:s'));
        $this->assertSame('2026-09-24 10:30:00', $parsed->format('Y-m-d H:i:s'));
    }

    public function testWhatTheDatetimeControlRendersIsAcceptedByTheValidatorItself(): void
    {
        // This walks the join instead of describing it: render the control,
        // take the value out of the DOM, post it back through the validator
        // that will actually judge it, and check it survives unchanged.
        //
        // What it catches is the template drawing a shape the validator
        // refuses -- a European date, say -- which would tell a person who
        // opened a form, touched nothing and saved that their own value is
        // not a date. What it does NOT catch is the T separator going away,
        // because the validator accepts the space form too; that is the test
        // above, and it has to assert the shape directly, since the failure
        // it guards is a browser silently drawing an empty control and no
        // amount of PHP can observe that. The two tests are not redundant:
        // one pins what the browser needs, this one pins what comes back.
        $stored = '2026-09-24 10:30:00';

        $xpath = $this->dom($this->renderer()->render(
            'region/form/field',
            $this->field(key: 'at', label: 'At', type: FieldType::Datetime, value: $stored),
        ));
        $rendered = $this->one($xpath, '//input[@type="datetime-local"]')->getAttribute('value');

        $form = new FormDefinition(['at' => new FieldDefinition(
            key: 'at',
            label: 'At',
            type: FieldType::Datetime,
            default: null,
            required: false,
            readonly: false,
            hidden: false,
            help: '',
            placeholder: '',
            options: [],
            min: null,
            max: null,
            pattern: null,
        )]);

        $result = (new FieldValidator())->validate($form, Submission::fromBody(['at' => $rendered], $form));

        $this->assertSame([], $result->errors(), "the validator refused '{$rendered}', which the control drew");
        $this->assertSame($stored, $result->values()['at'], 'the value came back as something other than it went in');
    }

    public function testADatetimeAlreadyInTheTFormIsLeftAlone(): void
    {
        $field = $this->field(key: 'at', label: 'At', type: FieldType::Datetime, value: '2026-09-24T10:30');

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));

        $this->assertSame('2026-09-24T10:30', $this->one($xpath, '//input[@type="datetime-local"]')->getAttribute('value'));
    }

    public function testAHiddenFieldIsAnInputWithNoLabelAndNoWrapper(): void
    {
        $field = $this->field(key: 'site_id', label: 'Site', type: FieldType::Hidden, value: '7');

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));

        $input = $this->one($xpath, '//input[@type="hidden"]');
        $this->assertSame('site_id', $input->getAttribute('name'));
        $this->assertSame('7', $input->getAttribute('value'));
        $this->assertSame([], $this->all($xpath, '//label'), 'a hidden field has nothing to label');
    }

    public function testAPasswordFieldNeverRedrawsWhatWasTyped(): void
    {
        $field = $this->field(key: 'secret', label: 'Secret', type: FieldType::Password, value: 'hunter2');

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="password"]');

        $this->assertSame('', $input->getAttribute('value'));
    }

    public function testARequiredFieldIsRequiredAndCarriesAVisibleMarker(): void
    {
        $field = $this->field(required: true);

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));

        $this->assertTrue($this->one($xpath, '//input[@type="text"]')->hasAttribute('required'));
        $this->assertCount(1, $this->all($xpath, '//*[' . $this->hasClass('ra-form-required') . ']'));
    }

    public function testAReadonlyTextFieldIsReadonlyRatherThanDisabledSoItsValueStillReads(): void
    {
        $field = $this->field(readonly: true);

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="text"]');

        $this->assertTrue($input->hasAttribute('readonly'));
    }

    public function testAReadonlyChoiceIsDisabledBecauseSelectsHaveNoReadonlyAttribute(): void
    {
        $field = $this->field(
            key: 'status',
            label: 'Status',
            type: FieldType::Select,
            value: 'live',
            options: $this->options('draft', 'live'),
            readonly: true,
        );

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));

        $this->assertTrue($this->one($xpath, '//select')->hasAttribute('disabled'));
    }

    public function testHelpTextIsAttachedToTheControlByDescribedBy(): void
    {
        $field = $this->field(help: 'Shown in the list.');

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="text"]');

        $described = $input->getAttribute('aria-describedby');
        $this->assertNotSame('', $described);
        $this->assertCount(1, $this->all($xpath, '//*[@id="' . $described . '"]'));
    }

    // --- errors: shown twice, and reachable both times ---

    public function testAFieldWithAnErrorIsInvalidAndDescribedByAMessageThatExists(): void
    {
        $field = $this->field(errors: ['Title is required.', 'Title is too short.']);

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));
        $input = $this->one($xpath, '//input[@type="text"]');

        $this->assertSame('true', $input->getAttribute('aria-invalid'));

        $ids = preg_split('/\s+/', trim($input->getAttribute('aria-describedby'))) ?: [];
        $this->assertNotSame([], $ids);

        $described = '';

        foreach ($ids as $id) {
            $elements = $this->all($xpath, '//*[@id="' . $id . '"]');
            $this->assertCount(1, $elements, "aria-describedby names '{$id}', which is not in the document");
            $described .= $elements[0]->textContent;
        }

        // The control has room for one message; the summary at the top of
        // the form carries the rest.
        $this->assertStringContainsString('Title is required.', $described);
    }

    public function testAFieldWithNoErrorIsNotMarkedInvalid(): void
    {
        $xpath = $this->dom($this->renderer()->render('region/form/field', $this->field()));

        $this->assertSame([], $this->all($xpath, '//*[@aria-invalid]'));
    }

    public function testTheSummaryCarriesEveryErrorOnTheFormAndLinksEachToItsField(): void
    {
        $form = $this->form(
            fields: [
                $this->field(key: 'title', label: 'Title', errors: ['Title is required.']),
                $this->field(
                    key: 'price',
                    label: 'Price',
                    type: FieldType::Number,
                    value: '',
                    errors: ['Price must be a number.'],
                ),
            ],
            errors: ['That slug is already taken.'],
        );

        $html = $this->renderer()->render('region/form/region', $form);
        $xpath = $this->dom($html);

        $items = $this->all($xpath, '//*[' . $this->hasClass('ra-form-errors') . ']//li');
        $this->assertCount(3, $items, 'a long form must not hide a failure below the fold');

        $links = $this->all($xpath, '//*[' . $this->hasClass('ra-form-errors') . ']//a[@href]');
        $this->assertCount(2, $links, 'a form-level error names no field to link to');

        foreach ($links as $link) {
            $target = ltrim($link->getAttribute('href'), '#');

            $this->assertCount(
                1,
                $this->all($xpath, '//*[@id="' . $target . '"]'),
                "the summary links to '#{$target}', which is not in the document",
            );
        }
    }

    public function testAFormWithNothingWrongShowsNoSummaryAtAll(): void
    {
        $xpath = $this->dom($this->renderer()->render('region/form/region', $this->form()));

        $this->assertSame([], $this->all($xpath, '//*[' . $this->hasClass('ra-form-errors') . ']'));
    }

    public function testTheErrorMessageIsNotHiddenByBootstrapsOwnValidationClasses(): void
    {
        // .invalid-feedback is display:none until a sibling carries
        // .is-invalid. An error nobody can read is the milestone 4 toast
        // again, so the message carries this project's own class instead.
        $field = $this->field(errors: ['Title is required.']);

        $html = $this->renderer()->render('region/form/field', $field);

        $this->assertStringNotContainsString('invalid-feedback', $html);
    }

    // --- actions ---

    public function testTheActionsOfferASubmitAndAWayBackToWhereThePersonCameFrom(): void
    {
        $html = $this->renderer()->render('region/form/region', $this->form(returnTo: '/admin/p/ads?page=3'));
        $xpath = $this->dom($html);

        $submit = $this->one($xpath, '//button[@type="submit"]');
        $this->assertNotSame('', trim($submit->textContent));

        $cancel = $this->one($xpath, '//*[' . $this->hasClass('ra-form-actions') . ']//a[@href]');
        $this->assertSame('/admin/p/ads?page=3', $cancel->getAttribute('href'));
    }

    // --- escaping ---

    public function testAScriptInAValueIsTextAndNeverAnElement(): void
    {
        $hostile = '<script>alert("x")</script> & "quoted" \'single\'';

        $form = $this->form(fields: [
            $this->field(value: $hostile, label: $hostile, help: $hostile, placeholder: $hostile, errors: [$hostile]),
        ]);

        $html = $this->renderer()->render('region/form/region', $form);
        $xpath = $this->dom($html);

        $this->assertSame([], $this->all($xpath, '//script'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame($hostile, $this->one($xpath, '//input[@type="text"]')->getAttribute('value'));
    }

    public function testATextareaDoesNotLetAValueCloseItsOwnTag(): void
    {
        $hostile = '</textarea><script>alert(1)</script>';

        $field = $this->field(key: 'body', label: 'Body', type: FieldType::Textarea, value: $hostile);

        $xpath = $this->dom($this->renderer()->render('region/form/field', $field));

        $this->assertSame([], $this->all($xpath, '//script'));
        $this->assertSame($hostile, $this->one($xpath, '//textarea')->textContent);
    }

    /** @return list<FormFieldView> one field of every type, for the sweeps above */
    private function everyType(): array
    {
        $fields = [];

        foreach (FieldType::cases() as $type) {
            $fields[] = $this->field(
                key: 'f_' . $type->value,
                label: ucfirst($type->value),
                type: $type,
                value: match (true) {
                    $type->isMultiple() => ['a'],
                    $type === FieldType::Checkbox => true,
                    $type === FieldType::Date => '2026-09-24',
                    $type === FieldType::Datetime => '2026-09-24 10:30:00',
                    default => 'a',
                },
                options: $type->takesOptions() ? $this->options('a', 'b') : [],
            );
        }

        return $fields;
    }
}
