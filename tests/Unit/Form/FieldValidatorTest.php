<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\EnumOption;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\Submission;
use RockAdmin\Form\ValidationError;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;

#[CoversClass(FieldValidator::class)]
#[CoversClass(Submission::class)]
#[CoversClass(ValidationError::class)]
final class FieldValidatorTest extends TestCase
{
    // --- What a Submission refuses to carry ---------------------------------

    public function testAKeyTheFormDoesNotDeclareIsDropped(): void
    {
        $form = $this->form([$this->field('title')]);

        $submission = Submission::fromBody(['title' => 'Hello', 'is_admin' => '1'], $form);

        $this->assertSame(['title' => 'Hello'], $submission->values());
        $this->assertFalse($submission->has('is_admin'));
    }

    public function testAReadonlyFieldCannotBeSetFromTheWire(): void
    {
        $form = $this->form([
            $this->field('title'),
            $this->field('created_at', readonly: true),
        ]);

        $submission = Submission::fromBody(['title' => 'Hello', 'created_at' => '1970-01-01'], $form);

        $this->assertSame(['title' => 'Hello'], $submission->values());
        $this->assertFalse($submission->has('created_at'));
    }

    public function testAHiddenFieldCannotBeSetFromTheWire(): void
    {
        $form = $this->form([
            $this->field('title'),
            $this->field('site_id', hidden: true),
        ]);

        $submission = Submission::fromBody(['title' => 'Hello', 'site_id' => '99'], $form);

        $this->assertSame(['title' => 'Hello'], $submission->values());
        $this->assertFalse($submission->has('site_id'));
        $this->assertNull($submission->value('site_id'));
    }

    // --- required -----------------------------------------------------------

    public function testARequiredFieldWithAnEmptyValueFails(): void
    {
        $form = $this->form([$this->field('title', label: 'Title', required: true)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['title' => ''], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
        $this->assertSame('Title is required.', $errors[0]->message);
    }

    public function testARequiredFieldThatIsAbsentEntirelyFails(): void
    {
        $form = $this->form([$this->field('title', required: true)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody([], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
    }

    public function testZeroIsNotEmpty(): void
    {
        $form = $this->form([$this->field('title', required: true)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['title' => '0'], $form));

        $this->assertSame([], $errors);
    }

    public function testARequiredCheckboxIsSatisfiedByBeingAbsentBecauseAbsentMeansFalse(): void
    {
        $form = $this->form([$this->field('active', FieldType::Checkbox, required: true)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody([], $form));

        $this->assertSame([], $errors);
    }

    // --- number -------------------------------------------------------------

    public function testANumberFieldRefusesSomethingThatIsNotANumber(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price')]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['price' => 'cheap'], $form));

        $this->assertSame(['price'], $this->fieldsOf($errors));
        $this->assertSame('Price must be a number.', $errors[0]->message);
    }

    public function testANumberBelowItsMinimumFails(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price', min: 10, max: 100)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['price' => '9'], $form));

        $this->assertSame(['price'], $this->fieldsOf($errors));
        $this->assertSame('Price must be at least 10.', $errors[0]->message);
    }

    public function testANumberAboveItsMaximumFails(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price', min: 10, max: 100)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['price' => '101'], $form));

        $this->assertSame(['price'], $this->fieldsOf($errors));
        $this->assertSame('Price must be at most 100.', $errors[0]->message);
    }

    public function testANumberInsideItsRangePasses(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, min: 10, max: 100)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['price' => '10.5'], $form));

        $this->assertSame([], $errors);
    }

    // --- text lengths -------------------------------------------------------

    public function testATextValueShorterThanItsMinimumFails(): void
    {
        $form = $this->form([$this->field('title', label: 'Title', min: 3)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['title' => 'ab'], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
        $this->assertSame('Title must be at least 3 characters.', $errors[0]->message);
    }

    public function testATextValueLongerThanItsMaximumFails(): void
    {
        $form = $this->form([$this->field('title', label: 'Title', max: 4)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['title' => 'abcde'], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
        $this->assertSame('Title must be at most 4 characters.', $errors[0]->message);
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // 'ěščř' is four characters and eight bytes. A max of four must
        // accept it, which strlen() would not.
        $form = $this->form([$this->field('title', max: 4)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['title' => 'ěščř'], $form));

        $this->assertSame([], $errors);
    }

    public function testATextareaAndAPasswordAreMeasuredTheSameWayAText(): void
    {
        $form = $this->form([
            $this->field('body', FieldType::Textarea, min: 5),
            $this->field('secret', FieldType::Password, min: 5),
        ]);

        $errors = (new FieldValidator())->validate(
            $form,
            Submission::fromBody(['body' => 'ab', 'secret' => 'cd'], $form),
        );

        $this->assertSame(['body', 'secret'], $this->fieldsOf($errors));
    }

    // --- pattern ------------------------------------------------------------

    public function testAValueThatDoesNotMatchThePatternFails(): void
    {
        $form = $this->form([$this->field('slug', label: 'Slug', pattern: '^[a-z-]+$')]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['slug' => 'Not A Slug'], $form));

        $this->assertSame(['slug'], $this->fieldsOf($errors));
        $this->assertSame('Slug is not in the required format.', $errors[0]->message);
    }

    public function testAValueThatMatchesThePatternPasses(): void
    {
        $form = $this->form([$this->field('slug', pattern: '^[a-z-]+$')]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['slug' => 'a-slug'], $form));

        $this->assertSame([], $errors);
    }

    public function testAPatternThatPcreGivesUpOnIsAFailureNotACrash(): void
    {
        // '(a+)+$' against a long run of 'a' ending in something else is the
        // textbook catastrophic backtrack: PCRE gives up at its backtrack
        // limit and preg_match() returns false rather than 0. The load-time
        // check cannot see this, because it runs the pattern against an
        // empty string and returns instantly.
        $form = $this->form([$this->field('slug', label: 'Slug', pattern: '^(a+)+$')]);
        $value = str_repeat('a', 40) . 'b';

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['slug' => $value], $form));

        $this->assertSame(['slug'], $this->fieldsOf($errors));
        $this->assertSame('Slug is not in the required format.', $errors[0]->message);
        $this->assertSame(PREG_BACKTRACK_LIMIT_ERROR, preg_last_error());
    }

    // --- options ------------------------------------------------------------

    public function testASelectRefusesAValueOutsideItsOptions(): void
    {
        $form = $this->form([
            $this->field('status', FieldType::Select, label: 'Status', options: ['draft', 'live']),
        ]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['status' => 'deleted'], $form));

        $this->assertSame(['status'], $this->fieldsOf($errors));
        $this->assertSame('Status is not one of the available options.', $errors[0]->message);
    }

    public function testARadioRefusesAValueOutsideItsOptions(): void
    {
        $form = $this->form([
            $this->field('status', FieldType::Radio, options: ['draft', 'live']),
        ]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['status' => 'deleted'], $form));

        $this->assertSame(['status'], $this->fieldsOf($errors));
    }

    public function testASelectAcceptsADeclaredOption(): void
    {
        $form = $this->form([
            $this->field('status', FieldType::Select, options: ['draft', 'live']),
        ]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['status' => 'live'], $form));

        $this->assertSame([], $errors);
    }

    public function testAMultiselectAcceptsAListOfDeclaredOptions(): void
    {
        $form = $this->form([
            $this->field('tags', FieldType::Multiselect, options: ['a', 'b', 'c']),
        ]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['tags' => ['a', 'c']], $form));

        $this->assertSame([], $errors);
    }

    public function testAMultiselectRefusesAnEntryOutsideItsOptions(): void
    {
        $form = $this->form([
            $this->field('tags', FieldType::Multiselect, label: 'Tags', options: ['a', 'b']),
        ]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['tags' => ['a', 'z']], $form));

        $this->assertSame(['tags'], $this->fieldsOf($errors));
        $this->assertSame('Tags is not one of the available options.', $errors[0]->message);
    }

    public function testAMultiselectRefusesAValueThatIsNotAList(): void
    {
        $form = $this->form([
            $this->field('tags', FieldType::Multiselect, label: 'Tags', options: ['a', 'b']),
        ]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['tags' => 'a'], $form));

        $this->assertSame(['tags'], $this->fieldsOf($errors));
        $this->assertSame('Tags must be a list of options.', $errors[0]->message);
    }

    // --- dates --------------------------------------------------------------

    public function testADateFieldAcceptsTheFormatItRenders(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['published_on' => '2026-03-14'], $form));

        $this->assertSame([], $errors);
    }

    public function testADateFieldRefusesSomethingThatIsNotADate(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date, label: 'Published on')]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['published_on' => 'tomorrow'], $form));

        $this->assertSame(['published_on'], $this->fieldsOf($errors));
        $this->assertSame('Published on is not a valid date.', $errors[0]->message);
    }

    public function testAnImpossibleDateIsRefusedRatherThanRolledForward(): void
    {
        // createFromFormat() accepts '2026-02-31' and quietly hands back
        // 3 March. Only the round trip through the same format, plus
        // getLastErrors(), catches it.
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['published_on' => '2026-02-31'], $form));

        $this->assertSame(['published_on'], $this->fieldsOf($errors));
    }

    public function testADateWithTrailingJunkIsRefused(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $errors = (new FieldValidator())->validate(
            $form,
            Submission::fromBody(['published_on' => '2026-03-14 and then some'], $form),
        );

        $this->assertSame(['published_on'], $this->fieldsOf($errors));
    }

    public function testADatetimeFieldAcceptsWhatABrowserDatetimeControlSends(): void
    {
        $form = $this->form([$this->field('published_at', FieldType::Datetime)]);

        $errors = (new FieldValidator())->validate(
            $form,
            Submission::fromBody(['published_at' => '2026-03-14T09:26'], $form),
        );

        $this->assertSame([], $errors);
    }

    public function testADatetimeFieldRefusesADateOnlyValue(): void
    {
        $form = $this->form([$this->field('published_at', FieldType::Datetime, label: 'Published at')]);

        $errors = (new FieldValidator())->validate(
            $form,
            Submission::fromBody(['published_at' => '2026-03-14'], $form),
        );

        $this->assertSame(['published_at'], $this->fieldsOf($errors));
        $this->assertSame('Published at is not a valid date and time.', $errors[0]->message);
    }

    // --- messages -----------------------------------------------------------

    public function testAMessageNamesTheLabelRatherThanTheKey(): void
    {
        $form = $this->form([$this->field('price_cents', FieldType::Number, label: 'Price')]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['price_cents' => 'lots'], $form));

        $this->assertSame('Price must be a number.', $errors[0]->message);
        $this->assertStringNotContainsString('price_cents', $errors[0]->message);
        $this->assertSame('price_cents', $errors[0]->field);
    }

    // --- collecting every failure -------------------------------------------

    public function testEveryFailureIsReportedNotJustTheFirst(): void
    {
        $form = $this->form([
            $this->field('title', label: 'Title', required: true),
            $this->field('price', FieldType::Number, label: 'Price'),
            $this->field('status', FieldType::Select, label: 'Status', options: ['draft', 'live']),
            $this->field('slug', label: 'Slug', pattern: '^[a-z-]+$'),
        ]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody([
            'title' => '',
            'price' => 'cheap',
            'status' => 'deleted',
            'slug' => 'Not A Slug',
        ], $form));

        $this->assertSame(['title', 'price', 'status', 'slug'], $this->fieldsOf($errors));
    }

    public function testOneFieldCanFailMoreThanOneRuleAtOnce(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price', max: 10, pattern: '^\d+$')]);

        $errors = (new FieldValidator())->validate($form, Submission::fromBody(['price' => '11.5'], $form));

        $this->assertSame(['price', 'price'], $this->fieldsOf($errors));
    }

    // --- coercion -----------------------------------------------------------

    public function testValuesAreCoercedToTheTypeTheFieldDeclared(): void
    {
        $form = $this->form([
            $this->field('title'),
            $this->field('quantity', FieldType::Number),
            $this->field('price', FieldType::Number),
            $this->field('active', FieldType::Checkbox),
            $this->field('tags', FieldType::Multiselect, options: ['a', 'b']),
            $this->field('published_on', FieldType::Date),
            $this->field('published_at', FieldType::Datetime),
        ]);

        $submission = Submission::fromBody([
            'title' => 'Hello',
            'quantity' => '7',
            'price' => '10.50',
            'active' => 'on',
            'tags' => ['a', 'b'],
            'published_on' => '2026-03-14',
            'published_at' => '2026-03-14T09:26',
        ], $form);

        $values = (new FieldValidator())->values($form, $submission);

        $this->assertSame([
            'title' => 'Hello',
            'quantity' => 7,
            'price' => 10.5,
            'active' => true,
            'tags' => ['a', 'b'],
            'published_on' => '2026-03-14',
            'published_at' => '2026-03-14 09:26:00',
        ], $values);
    }

    public function testAnUncheckedCheckboxIsFalseNotMissing(): void
    {
        $form = $this->form([$this->field('active', FieldType::Checkbox)]);

        $values = (new FieldValidator())->values($form, Submission::fromBody([], $form));

        $this->assertArrayHasKey('active', $values);
        $this->assertFalse($values['active']);
    }

    public function testAFieldTheSubmissionDidNotCarryIsNotInTheValues(): void
    {
        $form = $this->form([$this->field('title'), $this->field('subtitle')]);

        $values = (new FieldValidator())->values($form, Submission::fromBody(['title' => 'Hello'], $form));

        $this->assertSame(['title' => 'Hello'], $values);
    }

    public function testAnEmptyOptionalValueIsCoercedToNullRatherThanAnEmptyString(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $values = (new FieldValidator())->values($form, Submission::fromBody(['published_on' => ''], $form));

        $this->assertNull($values['published_on']);
    }

    // --- helpers ------------------------------------------------------------

    /**
     * @param list<ValidationError> $errors
     *
     * @return list<string>
     */
    private function fieldsOf(array $errors): array
    {
        return array_map(static fn (ValidationError $error): string => $error->field, $errors);
    }

    /** @param list<FieldDefinition> $fields */
    private function form(array $fields): FormDefinition
    {
        $keyed = [];

        foreach ($fields as $field) {
            $keyed[$field->key] = $field;
        }

        return new FormDefinition($keyed);
    }

    /** @param list<string> $options */
    private function field(
        string $key,
        FieldType $type = FieldType::Text,
        string $label = '',
        bool $required = false,
        bool $readonly = false,
        bool $hidden = false,
        array $options = [],
        ?int $min = null,
        ?int $max = null,
        ?string $pattern = null,
    ): FieldDefinition {
        $enum = [];

        foreach ($options as $option) {
            $enum[$option] = new EnumOption($option, ucfirst($option));
        }

        return new FieldDefinition(
            key: $key,
            label: $label === '' ? ucfirst(str_replace('_', ' ', $key)) : $label,
            type: $type,
            default: null,
            required: $required,
            readonly: $readonly,
            hidden: $hidden,
            help: '',
            placeholder: '',
            options: $enum,
            min: $min,
            max: $max,
            pattern: $pattern,
        );
    }
}
