<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\Enums;
use RockAdmin\Config\Placeholder;
use RockAdmin\Page\FieldType;
use RockAdmin\Page\FormDefinition;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionType;

/**
 * A form region loads into a `FormDefinition` of `FieldDefinition`s the way a
 * list region loads into columns: `PageRepositoryTest` covers the shared
 * loading pipeline, this file covers what is specific to a form.
 */
#[CoversClass(PageRepository::class)]
final class PageRepositoryFormTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()) . '/ra-pages-form-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/pages', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function writePage(string $name, string $body): void
    {
        file_put_contents($this->root . '/pages/' . $name . '.php', "<?php\n\nreturn {$body};\n");
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }

    private function repository(?Enums $enums = null): PageRepository
    {
        return new PageRepository(
            $this->root . '/pages',
            static fn (string $name): ?string => null,
            $enums ?? Enums::fromConfig([]),
        );
    }

    private function form(string $pageName = 'ads', string $regionKey = 'form', ?Enums $enums = null): FormDefinition
    {
        $form = $this->repository($enums)->get($pageName)->region($regionKey)->form;

        $this->assertNotNull($form);

        return $form;
    }

    public function testAFormLoadsWithItsFieldsKeyedByName(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => [
                                'title' => ['type' => 'text'],
                                'body' => ['type' => 'textarea'],
                            ],
                        ],
                    ],
                ],
            ]
            PHP);

        $form = $this->repository()->get('ads')->region('form')->form;

        $this->assertNotNull($form);
        $this->assertTrue($form->hasField('title'));
        $this->assertTrue($form->hasField('body'));
        $this->assertSame(FieldType::Text, $form->field('title')->type);
        $this->assertSame(FieldType::Textarea, $form->field('body')->type);
    }

    public function testAFieldsLabelDefaultsFromItsKey(): void
    {
        $this->writePage('ads', $this->formPage(['created_at' => ['type' => 'text']]));

        $field = $this->form()->field('created_at');

        $this->assertSame('Created at', $field->label);
    }

    public function testAnExplicitLabelIsKept(): void
    {
        $this->writePage('ads', $this->formPage(['title' => ['type' => 'text', 'label' => 'Ad Title']]));

        $field = $this->form()->field('title');

        $this->assertSame('Ad Title', $field->label);
    }

    public function testEditableExcludesReadonlyFields(): void
    {
        $this->writePage('ads', $this->formPage([
            'title' => ['type' => 'text'],
            'created_at' => ['type' => 'text', 'readonly' => true],
        ]));

        $editable = $this->form()->editable();

        $this->assertArrayHasKey('title', $editable);
        $this->assertArrayNotHasKey('created_at', $editable);
    }

    public function testEditableExcludesHiddenFields(): void
    {
        $this->writePage('ads', $this->formPage([
            'title' => ['type' => 'text'],
            'site_id' => ['type' => 'hidden', 'hidden' => true, 'default' => '{{workspace.site_id}}'],
        ]));

        $editable = $this->form()->editable();

        $this->assertArrayHasKey('title', $editable);
        $this->assertArrayNotHasKey('site_id', $editable);
    }

    public function testEditableKeepsAnOrdinaryField(): void
    {
        $this->writePage('ads', $this->formPage(['title' => ['type' => 'text']]));

        $this->assertArrayHasKey('title', $this->form()->editable());
    }

    public function testAnEnumReferenceResolvesIntoOptions(): void
    {
        $this->writePage('ads', $this->formPage([
            'state' => ['type' => 'select', 'options' => '@enum:ad_state'],
        ]));

        $enums = Enums::fromConfig(['ad_state' => [
            'active' => ['label' => 'Active'],
            'inactive' => ['label' => 'Inactive'],
        ]]);
        $field = $this->form(enums: $enums)->field('state');

        $this->assertArrayHasKey('active', $field->options);
        $this->assertSame('Active', $field->options['active']->label);
    }

    public function testAPlaceholderDefaultSurvivesAsAPlaceholderObject(): void
    {
        $this->writePage('ads', $this->formPage([
            'site_id' => ['type' => 'hidden', 'default' => '{{workspace.site_id}}'],
        ]));

        $field = $this->form()->field('site_id');

        $this->assertInstanceOf(Placeholder::class, $field->default);
        $this->assertSame('workspace', $field->default->namespace);
        $this->assertSame('site_id', $field->default->name);
    }

    public function testACopyResetListIsRead(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => ['title' => ['type' => 'text'], 'state' => ['type' => 'text']],
                            'copy' => ['reset' => ['state']],
                        ],
                    ],
                ],
            ]
            PHP);

        $this->assertSame(['state'], $this->form()->resetOnCopy);
    }

    public function testANonFormRegionHasNoForm(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->assertNull($this->repository()->get('ads')->region('grid')->form);
    }

    public function testAPageWithNoFormRegionAnswersNullFromFirstFormRegion(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => ['grid' => ['type' => 'list', 'columns' => ['title' => []]]],
            ]
            PHP);

        $this->assertNull($this->repository()->get('ads')->firstFormRegion());
    }

    public function testFirstFormRegionFindsTheFormRegion(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'grid' => ['type' => 'list', 'columns' => ['title' => []]],
                    'form' => ['type' => 'form', 'form' => ['fields' => ['title' => ['type' => 'text']]]],
                ],
            ]
            PHP);

        $region = $this->repository()->get('ads')->firstFormRegion();

        $this->assertNotNull($region);
        $this->assertSame('form', $region->key);
        $this->assertSame(RegionType::Form, $region->type);
    }

    public function testAnUnknownFieldTypeIsRefused(): void
    {
        $this->writePage('ads', $this->formPage(['title' => ['type' => 'nuber']]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'title'");

        $this->repository()->get('ads');
    }

    public function testOptionsOnATypeThatTakesNoneIsRefused(): void
    {
        $this->writePage('ads', $this->formPage([
            'title' => ['type' => 'text', 'options' => ['a' => 'A']],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'title'");

        $this->repository()->get('ads');
    }

    public function testATypeThatTakesOptionsWithNoneIsRefused(): void
    {
        $this->writePage('ads', $this->formPage([
            'state' => ['type' => 'select'],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'state'");

        $this->repository()->get('ads');
    }

    public function testAnInvalidPatternIsRefused(): void
    {
        $this->writePage('ads', $this->formPage([
            'title' => ['type' => 'text', 'pattern' => '[A-Z'],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'title'");

        $this->repository()->get('ads');
    }

    public function testAPatternOnAMultiselectIsRefused(): void
    {
        // A multiselect's value is a list, and a pattern can only be run
        // against a scalar, so the key would be silently dead. Refusing
        // configuration that does nothing is what this loader is for.
        $this->writePage('ads', $this->formPage([
            'tags' => ['type' => 'multiselect', 'options' => ['a' => 'A'], 'pattern' => '[a-z]+'],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'tags'");

        $this->repository()->get('ads');
    }

    public function testAPatternOnACheckboxIsRefused(): void
    {
        // Same reason: a checkbox carries one bit, present or absent, and
        // never reaches a pattern check.
        $this->writePage('ads', $this->formPage([
            'active' => ['type' => 'checkbox', 'pattern' => '[a-z]+'],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'active'");

        $this->repository()->get('ads');
    }

    public function testMinGreaterThanMaxIsRefused(): void
    {
        $this->writePage('ads', $this->formPage([
            'quantity' => ['type' => 'number', 'min' => 10, 'max' => 1],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'quantity'");

        $this->repository()->get('ads');
    }

    public function testACopyResetNamingAnUndeclaredFieldIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => ['title' => ['type' => 'text']],
                            'copy' => ['reset' => ['nope']],
                        ],
                    ],
                ],
            ]
            PHP);

        // Two expectExceptionMessage() calls assign the same property, so the
        // second silently replaced the first and only 'nope' was ever checked.
        try {
            $this->repository()->get('ads');
            $this->fail('A copy.reset entry naming an undeclared field should be refused.');
        } catch (PageException $e) {
            $this->assertStringContainsString("Page 'ads'", $e->getMessage());
            $this->assertStringContainsString('nope', $e->getMessage());
        }
    }

    public function testRequiredTogetherWithReadonlyIsRefused(): void
    {
        $this->writePage('ads', $this->formPage([
            'title' => ['type' => 'text', 'required' => true, 'readonly' => true],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage("Page 'ads': field 'title'");

        $this->repository()->get('ads');
    }

    public function testAMalformedOptionOnAFieldNamesTheFieldNotAColumn(): void
    {
        $this->writePage('ads', $this->formPage([
            'state' => ['type' => 'select', 'options' => ['bad' => ['nope' => 'x']]],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage(
            "Page 'ads': field 'state': the option 'bad' is neither a label nor ['label' => ..., 'color' => ...].",
        );

        $this->repository()->get('ads');
    }

    public function testAnEmptyOptionsListOnATypeThatTakesThemIsRefused(): void
    {
        $this->writePage('ads', $this->formPage([
            'state' => ['type' => 'select', 'options' => []],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage(
            "Page 'ads': field 'state' has type 'select', which needs 'options', but none were given.",
        );

        $this->repository()->get('ads');
    }

    public function testRequiredAndHiddenWithADefaultLoads(): void
    {
        $this->writePage('ads', $this->formPage([
            'site_id' => [
                'type' => 'hidden',
                'hidden' => true,
                'required' => true,
                'default' => '{{workspace.site_id}}',
            ],
        ]));

        $field = $this->form()->field('site_id');

        $this->assertTrue($field->required);
        $this->assertTrue($field->hidden);
        $this->assertInstanceOf(Placeholder::class, $field->default);
    }

    public function testRequiredAndHiddenWithoutADefaultIsRefused(): void
    {
        $this->writePage('ads', $this->formPage([
            'site_id' => ['type' => 'hidden', 'hidden' => true, 'required' => true],
        ]));

        $this->expectException(PageException::class);
        $this->expectExceptionMessage(
            "Page 'ads': field 'site_id' is both 'required' and 'hidden' but declares no 'default'. A hidden "
                . 'field takes its value from its default when the row is saved, so nothing could ever satisfy '
                . "'required'.",
        );

        $this->repository()->get('ads');
    }

    public function testAnUnknownDefaultTokenIsRefusedNamingBothValidTokens(): void
    {
        $this->writePage('ads', $this->formPage([
            'code' => ['type' => 'text', 'default' => '@nwo'],
        ]));

        try {
            $this->repository()->get('ads');
            $this->fail('An unknown default token should be refused at load.');
        } catch (PageException $e) {
            $this->assertStringContainsString("Page 'ads': field 'code'", $e->getMessage());
            $this->assertStringContainsString('@now', $e->getMessage());
            $this->assertStringContainsString('@uuid', $e->getMessage());
        }
    }

    public function testAFormBlockOnANonFormRegionIsRefused(): void
    {
        $this->writePage('ads', <<<'PHP'
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'grid' => [
                        'type' => 'list',
                        'columns' => ['title' => []],
                        'form' => ['fields' => ['title' => ['type' => 'text']]],
                    ],
                ],
            ]
            PHP);

        $this->expectException(PageException::class);
        $this->expectExceptionMessage(
            "Page 'ads': region 'grid' has a 'form' block, but its type is 'list', not 'form'.",
        );

        $this->repository()->get('ads');
    }

    /** @param array<string, array<string, mixed>> $fields */
    private function formPage(array $fields): string
    {
        $encoded = var_export($fields, true);

        return <<<PHP
            [
                'title' => 'Ads',
                'entity' => ['table' => 'ads'],
                'regions' => [
                    'form' => [
                        'type' => 'form',
                        'form' => [
                            'fields' => {$encoded},
                        ],
                    ],
                ],
            ]
            PHP;
    }
}
