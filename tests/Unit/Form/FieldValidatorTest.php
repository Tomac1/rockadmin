<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\EnumOption;
use RockAdmin\Form\DefaultValue;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\Submission;
use RockAdmin\Form\ValidationError;
use RockAdmin\Form\ValidationResult;
use RockAdmin\Page\FieldDefinition;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;

#[CoversClass(FieldValidator::class)]
#[CoversClass(Submission::class)]
#[CoversClass(ValidationError::class)]
#[CoversClass(ValidationResult::class)]
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

        $errors = $this->errorsFor($form, Submission::fromBody(['title' => ''], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
        $this->assertSame('Title is required.', $errors[0]->message);
    }

    public function testARequiredFieldThatIsAbsentEntirelyFails(): void
    {
        $form = $this->form([$this->field('title', required: true)]);

        $errors = $this->errorsFor($form, Submission::fromBody([], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
    }

    public function testZeroIsNotEmpty(): void
    {
        $form = $this->form([$this->field('title', required: true)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['title' => '0'], $form));

        $this->assertSame([], $errors);
    }

    public function testARequiredCheckboxMustBeTicked(): void
    {
        // `required` reads differently on a checkbox than on anything else:
        // not "may not be left blank" but "must be ticked", which is what it
        // means on every form anybody has ever filled in.
        $form = $this->form([$this->field('terms', FieldType::Checkbox, label: 'Terms', required: true)]);

        $errors = $this->errorsFor($form, Submission::fromBody([], $form));

        $this->assertSame(['terms'], $this->fieldsOf($errors));
        $this->assertSame('Terms must be ticked.', $errors[0]->message);
    }

    public function testARequiredCheckboxThatIsTickedPasses(): void
    {
        $form = $this->form([$this->field('terms', FieldType::Checkbox, required: true)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['terms' => 'on'], $form));

        $this->assertSame([], $errors);
    }

    public function testAnOptionalCheckboxLeftUntickedPasses(): void
    {
        $form = $this->form([$this->field('active', FieldType::Checkbox)]);

        $errors = $this->errorsFor($form, Submission::fromBody([], $form));

        $this->assertSame([], $errors);
    }

    // --- number -------------------------------------------------------------

    public function testANumberFieldRefusesSomethingThatIsNotANumber(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price')]);

        $errors = $this->errorsFor($form, Submission::fromBody(['price' => 'cheap'], $form));

        $this->assertSame(['price'], $this->fieldsOf($errors));
        $this->assertSame('Price must be a number.', $errors[0]->message);
    }

    public function testANumberBelowItsMinimumFails(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price', min: 10, max: 100)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['price' => '9'], $form));

        $this->assertSame(['price'], $this->fieldsOf($errors));
        $this->assertSame('Price must be at least 10.', $errors[0]->message);
    }

    public function testANumberAboveItsMaximumFails(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price', min: 10, max: 100)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['price' => '101'], $form));

        $this->assertSame(['price'], $this->fieldsOf($errors));
        $this->assertSame('Price must be at most 100.', $errors[0]->message);
    }

    public function testANumberInsideItsRangePasses(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number, min: 10, max: 100)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['price' => '10.5'], $form));

        $this->assertSame([], $errors);
    }

    public function testANumberWithSurroundingWhitespaceIsRefused(): void
    {
        // is_numeric() accepts a leading space, which then coerces to a
        // float and defeats the rule that an integral literal stays an
        // integer. A number control does not post a space in the first
        // place, so a value carrying one was not typed into one.
        $form = $this->form([$this->field('price', FieldType::Number, label: 'Price')]);

        $this->assertSame(['price'], $this->keysFor($form, Submission::fromBody(['price' => ' 12'], $form)));
        $this->assertSame(['price'], $this->keysFor($form, Submission::fromBody(['price' => "12\n"], $form)));
    }

    public function testScientificNotationIsRefused(): void
    {
        // A number control posts digits. '1e400' is also INF once cast,
        // which PDO cannot bind.
        $form = $this->form([$this->field('price', FieldType::Number)]);

        $this->assertSame(['price'], $this->keysFor($form, Submission::fromBody(['price' => '1e3'], $form)));
        $this->assertSame(['price'], $this->keysFor($form, Submission::fromBody(['price' => '1e400'], $form)));
        $this->assertSame(['price'], $this->keysFor($form, Submission::fromBody(['price' => '0x1A'], $form)));
    }

    public function testAnOrdinaryDecimalIsStillANumber(): void
    {
        $form = $this->form([$this->field('price', FieldType::Number)]);

        foreach (['12', '-12', '+12', '12.5', '-0.5', '.5', '0'] as $value) {
            $this->assertSame(
                [],
                $this->keysFor($form, Submission::fromBody(['price' => $value], $form)),
                "'{$value}' is a number a control can post.",
            );
        }
    }

    // --- text lengths -------------------------------------------------------

    public function testATextValueShorterThanItsMinimumFails(): void
    {
        $form = $this->form([$this->field('title', label: 'Title', min: 3)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['title' => 'ab'], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
        $this->assertSame('Title must be at least 3 characters.', $errors[0]->message);
    }

    public function testATextValueLongerThanItsMaximumFails(): void
    {
        $form = $this->form([$this->field('title', label: 'Title', max: 4)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['title' => 'abcde'], $form));

        $this->assertSame(['title'], $this->fieldsOf($errors));
        $this->assertSame('Title must be at most 4 characters.', $errors[0]->message);
    }

    public function testLengthIsCountedInCharactersNotBytes(): void
    {
        // 'ěščř' is four characters and eight bytes. A max of four must
        // accept it, which strlen() would not.
        $form = $this->form([$this->field('title', max: 4)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['title' => 'ěščř'], $form));

        $this->assertSame([], $errors);
    }

    public function testATextareaAndAPasswordAreMeasuredTheSameWayAText(): void
    {
        $form = $this->form([
            $this->field('body', FieldType::Textarea, min: 5),
            $this->field('secret', FieldType::Password, min: 5),
        ]);

        $errors = $this->errorsFor(
            $form,
            Submission::fromBody(['body' => 'ab', 'secret' => 'cd'], $form),
        );

        $this->assertSame(['body', 'secret'], $this->fieldsOf($errors));
    }

    // --- pattern ------------------------------------------------------------

    public function testAValueThatDoesNotMatchThePatternFails(): void
    {
        $form = $this->form([$this->field('slug', label: 'Slug', pattern: '^[a-z-]+$')]);

        $errors = $this->errorsFor($form, Submission::fromBody(['slug' => 'Not A Slug'], $form));

        $this->assertSame(['slug'], $this->fieldsOf($errors));
        $this->assertSame('Slug is not in the required format.', $errors[0]->message);
    }

    public function testAValueThatMatchesThePatternPasses(): void
    {
        $form = $this->form([$this->field('slug', pattern: '^[a-z-]+$')]);

        $errors = $this->errorsFor($form, Submission::fromBody(['slug' => 'a-slug'], $form));

        $this->assertSame([], $errors);
    }

    public function testAPatternIsAnchoredAtBothEnds(): void
    {
        // The HTML `pattern` attribute this mirrors is implicitly anchored
        // `^(?:...)$` by every browser. An unanchored server-side check would
        // be strictly weaker than the client-side hint, which on the only
        // content rule configuration can put on a free-text field is the one
        // direction that is never acceptable.
        $form = $this->form([$this->field('code', label: 'Code', pattern: '[A-Z]{2}\d{4}')]);

        foreach (['xxAB1234yy', "'; DROP TABLE users; -- AB1234", 'AB1234yy', 'xxAB1234'] as $value) {
            $this->assertSame(
                ['code'],
                $this->keysFor($form, Submission::fromBody(['code' => $value], $form)),
                "'{$value}' must not satisfy an anchored pattern.",
            );
        }

        $this->assertSame([], $this->keysFor($form, Submission::fromBody(['code' => 'AB1234'], $form)));
    }

    public function testAnAuthorsTopLevelAlternationIsAnchoredAsAWhole(): void
    {
        // Anchoring as '^a|b$' would anchor only the first branch, so 'xb'
        // would pass. The non-capturing group around the whole pattern is
        // what stops that.
        $form = $this->form([$this->field('code', pattern: 'yes|no')]);

        $this->assertSame([], $this->keysFor($form, Submission::fromBody(['code' => 'no'], $form)));
        $this->assertSame(['code'], $this->keysFor($form, Submission::fromBody(['code' => 'xno'], $form)));
        $this->assertSame(['code'], $this->keysFor($form, Submission::fromBody(['code' => 'yesx'], $form)));
    }

    public function testAPatternDoesNotAdmitATrailingNewline(): void
    {
        // Without the D modifier '$' matches before a final newline, so
        // "AB1234\n" would pass and then be written verbatim.
        $form = $this->form([$this->field('code', pattern: '[A-Z]{2}\d{4}')]);

        $this->assertSame(['code'], $this->keysFor($form, Submission::fromBody(['code' => "AB1234\n"], $form)));
    }

    public function testAnAuthorAnchoredPatternDoesNotAdmitATrailingNewlineEither(): void
    {
        $form = $this->form([$this->field('code', pattern: '^[A-Z]{2}\d{4}$')]);

        $this->assertSame(['code'], $this->keysFor($form, Submission::fromBody(['code' => "AB1234\n"], $form)));
        $this->assertSame([], $this->keysFor($form, Submission::fromBody(['code' => 'AB1234'], $form)));
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

        $errors = $this->errorsFor($form, Submission::fromBody(['slug' => $value], $form));

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

        $errors = $this->errorsFor($form, Submission::fromBody(['status' => 'deleted'], $form));

        $this->assertSame(['status'], $this->fieldsOf($errors));
        $this->assertSame('Status is not one of the available options.', $errors[0]->message);
    }

    public function testARadioRefusesAValueOutsideItsOptions(): void
    {
        $form = $this->form([
            $this->field('status', FieldType::Radio, options: ['draft', 'live']),
        ]);

        $errors = $this->errorsFor($form, Submission::fromBody(['status' => 'deleted'], $form));

        $this->assertSame(['status'], $this->fieldsOf($errors));
    }

    public function testASelectAcceptsADeclaredOption(): void
    {
        $form = $this->form([
            $this->field('status', FieldType::Select, options: ['draft', 'live']),
        ]);

        $errors = $this->errorsFor($form, Submission::fromBody(['status' => 'live'], $form));

        $this->assertSame([], $errors);
    }

    public function testAMultiselectAcceptsAListOfDeclaredOptions(): void
    {
        $form = $this->form([
            $this->field('tags', FieldType::Multiselect, options: ['a', 'b', 'c']),
        ]);

        $errors = $this->errorsFor($form, Submission::fromBody(['tags' => ['a', 'c']], $form));

        $this->assertSame([], $errors);
    }

    public function testAMultiselectRefusesAnEntryOutsideItsOptions(): void
    {
        $form = $this->form([
            $this->field('tags', FieldType::Multiselect, label: 'Tags', options: ['a', 'b']),
        ]);

        $errors = $this->errorsFor($form, Submission::fromBody(['tags' => ['a', 'z']], $form));

        $this->assertSame(['tags'], $this->fieldsOf($errors));
        $this->assertSame('Tags is not one of the available options.', $errors[0]->message);
    }

    public function testAMultiselectRefusesAValueThatIsNotAList(): void
    {
        $form = $this->form([
            $this->field('tags', FieldType::Multiselect, label: 'Tags', options: ['a', 'b']),
        ]);

        $errors = $this->errorsFor($form, Submission::fromBody(['tags' => 'a'], $form));

        $this->assertSame(['tags'], $this->fieldsOf($errors));
        $this->assertSame('Tags must be a list of options.', $errors[0]->message);
    }

    // --- dates --------------------------------------------------------------

    public function testADateFieldAcceptsTheFormatItRenders(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['published_on' => '2026-03-14'], $form));

        $this->assertSame([], $errors);
    }

    public function testADateFieldRefusesSomethingThatIsNotADate(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date, label: 'Published on')]);

        $errors = $this->errorsFor($form, Submission::fromBody(['published_on' => 'tomorrow'], $form));

        $this->assertSame(['published_on'], $this->fieldsOf($errors));
        $this->assertSame('Published on is not a valid date.', $errors[0]->message);
    }

    public function testAnImpossibleDateIsRefusedRatherThanRolledForward(): void
    {
        // createFromFormat() accepts '2026-02-31' and quietly hands back
        // 3 March. Only the round trip through the same format, plus
        // getLastErrors(), catches it.
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $errors = $this->errorsFor($form, Submission::fromBody(['published_on' => '2026-02-31'], $form));

        $this->assertSame(['published_on'], $this->fieldsOf($errors));
    }

    public function testADateWithTrailingJunkIsRefused(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $errors = $this->errorsFor(
            $form,
            Submission::fromBody(['published_on' => '2026-03-14 and then some'], $form),
        );

        $this->assertSame(['published_on'], $this->fieldsOf($errors));
    }

    public function testADatetimeFieldAcceptsWhatABrowserDatetimeControlSends(): void
    {
        $form = $this->form([$this->field('published_at', FieldType::Datetime)]);

        $errors = $this->errorsFor(
            $form,
            Submission::fromBody(['published_at' => '2026-03-14T09:26'], $form),
        );

        $this->assertSame([], $errors);
    }

    public function testADatetimeFieldRefusesADateOnlyValue(): void
    {
        $form = $this->form([$this->field('published_at', FieldType::Datetime, label: 'Published at')]);

        $errors = $this->errorsFor(
            $form,
            Submission::fromBody(['published_at' => '2026-03-14'], $form),
        );

        $this->assertSame(['published_at'], $this->fieldsOf($errors));
        $this->assertSame('Published at is not a valid date and time.', $errors[0]->message);
    }

    // --- the seam with DefaultValue -----------------------------------------

    public function testWhatDefaultValueProducesForADateFieldValidatesAndCoercesToItself(): void
    {
        // The join between Task 3 and Task 4: a create form draws a field at
        // its default, and the person saves it untouched. What DefaultValue
        // wrote into the control therefore comes straight back through here,
        // and must survive both the check and the coercion unchanged — or a
        // form nobody edited fails to save.
        $form = $this->form([$this->field('published_on', FieldType::Date, default: '@now')]);
        $default = DefaultValue::for($form->field('published_on'));

        $this->assertIsString($default);

        $submission = Submission::fromBody(['published_on' => $default], $form);

        $this->assertSame([], $this->errorsFor($form, $submission));
        $this->assertSame($default, $this->valuesFor($form, $submission)['published_on']);
    }

    public function testWhatDefaultValueProducesForADatetimeFieldValidatesAndCoercesToItself(): void
    {
        $form = $this->form([$this->field('published_at', FieldType::Datetime, default: '@now')]);
        $default = DefaultValue::for($form->field('published_at'));

        $this->assertIsString($default);

        $submission = Submission::fromBody(['published_at' => $default], $form);

        $this->assertSame([], $this->errorsFor($form, $submission));
        $this->assertSame($default, $this->valuesFor($form, $submission)['published_at']);
    }

    // --- messages -----------------------------------------------------------

    public function testAMessageNamesTheLabelRatherThanTheKey(): void
    {
        $form = $this->form([$this->field('price_cents', FieldType::Number, label: 'Price')]);

        $errors = $this->errorsFor($form, Submission::fromBody(['price_cents' => 'lots'], $form));

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

        $errors = $this->errorsFor($form, Submission::fromBody([
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

        $errors = $this->errorsFor($form, Submission::fromBody(['price' => '11.5'], $form));

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

        $values = $this->valuesFor($form, $submission);

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

        $values = $this->valuesFor($form, Submission::fromBody([], $form));

        $this->assertArrayHasKey('active', $values);
        $this->assertFalse($values['active']);
    }

    public function testAFieldTheSubmissionDidNotCarryIsNotInTheValues(): void
    {
        $form = $this->form([$this->field('title'), $this->field('subtitle')]);

        $values = $this->valuesFor($form, Submission::fromBody(['title' => 'Hello'], $form));

        $this->assertSame(['title' => 'Hello'], $values);
    }

    public function testAnEmptyOptionalValueIsCoercedToNullRatherThanAnEmptyString(): void
    {
        $form = $this->form([$this->field('published_on', FieldType::Date)]);

        $values = $this->valuesFor($form, Submission::fromBody(['published_on' => ''], $form));

        $this->assertNull($values['published_on']);
    }

    public function testAnEmptyMultiselectCoercesToAnEmptyListRatherThanNull(): void
    {
        // The null rule is about scalar columns: a nullable one gets NULL
        // rather than ''. A list column wants an empty list, which is a
        // different absence.
        $form = $this->form([$this->field('tags', FieldType::Multiselect, options: ['a', 'b'])]);

        $values = $this->valuesFor($form, Submission::fromBody(['tags' => []], $form));

        $this->assertSame([], $values['tags']);
    }

    public function testALargeIntegerKeepsItsPrecisionRatherThanBecomingAFloat(): void
    {
        $form = $this->form([$this->field('bigint', FieldType::Number)]);

        $values = $this->valuesFor($form, Submission::fromBody(['bigint' => '99999999999999999999'], $form));

        // Out of int range. Casting to float would silently lose digits, so
        // the literal travels as it arrived and the column decides.
        $this->assertSame('99999999999999999999', $values['bigint']);
    }

    // --- the ordering contract ----------------------------------------------

    public function testAskingForTheValuesOfAFailedValidationRaises(): void
    {
        // The two-call shape this replaced could hand back a value the
        // validator had just refused, guarded only by a docblock saying
        // "call after validate()". A result object makes the misuse
        // impossible instead of documenting it.
        $form = $this->form([$this->field('status', FieldType::Select, options: ['draft', 'live'])]);

        $result = (new FieldValidator())->validate($form, Submission::fromBody(['status' => 'deleted'], $form));

        $this->assertTrue($result->failed());
        $this->assertFalse($result->passed());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('did not pass validation');

        $result->values();
    }

    public function testAValueTheValidatorRefusedIsNeverHandedBack(): void
    {
        $form = $this->form([
            $this->field('title'),
            $this->field('status', FieldType::Select, options: ['draft', 'live']),
        ]);

        $result = (new FieldValidator())->validate(
            $form,
            Submission::fromBody(['title' => 'Hello', 'status' => 'deleted'], $form),
        );

        // Not even the fields that passed: a partially valid submission is
        // not a thing a WriteHandler may act on.
        try {
            $result->values();
            $this->fail('A failed result handed back its values.');
        } catch (\LogicException $exception) {
            $this->assertStringNotContainsString('deleted', $exception->getMessage());
        }
    }

    public function testAPassedResultHandsBackItsValues(): void
    {
        $form = $this->form([$this->field('title')]);

        $result = (new FieldValidator())->validate($form, Submission::fromBody(['title' => 'Hello'], $form));

        $this->assertTrue($result->passed());
        $this->assertSame([], $result->errors());
        $this->assertSame(['title' => 'Hello'], $result->values());
    }

    // --- helpers ------------------------------------------------------------

    /** @return list<ValidationError> */
    private function errorsFor(FormDefinition $form, Submission $submission): array
    {
        return (new FieldValidator())->validate($form, $submission)->errors();
    }

    /** @return list<string> the key of each field that failed, in order */
    private function keysFor(FormDefinition $form, Submission $submission): array
    {
        return $this->fieldsOf($this->errorsFor($form, $submission));
    }

    /** @return array<array-key, mixed> */
    private function valuesFor(FormDefinition $form, Submission $submission): array
    {
        return (new FieldValidator())->validate($form, $submission)->values();
    }

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
        mixed $default = null,
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
            default: $default,
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
