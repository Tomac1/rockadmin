<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ActionReference;
use RockAdmin\Config\ConfigException;
use RockAdmin\Config\EnumReference;
use RockAdmin\Config\PageReference;
use RockAdmin\Config\RegionReference;
use RockAdmin\Config\Resolver;

/**
 * The @page:, @action: and @region: references of specification 8.5.
 *
 * `ReferenceTest` is taken by the schema-to-Markdown generator, so the name
 * of this file says what it is about rather than colliding with it.
 */
#[CoversClass(PageReference::class)]
#[CoversClass(ActionReference::class)]
#[CoversClass(Resolver::class)]
final class ReferenceResolutionTest extends TestCase
{
    private function resolve(mixed $value): mixed
    {
        $resolver = new Resolver(static fn (string $name): ?string => null);

        return $resolver->resolve(['to' => $value])['to'];
    }

    public function testAPageReferenceCarriesThePageItNames(): void
    {
        $reference = $this->resolve('@page:users');

        $this->assertInstanceOf(PageReference::class, $reference);
        $this->assertSame('users', $reference->page);
        $this->assertSame('', $reference->suffix);
        $this->assertSame('@page:users', (string) $reference);
    }

    public function testAPageReferenceKeepsTheRestOfThePathAsWritten(): void
    {
        $reference = $this->resolve('@page:users/{id}/edit');

        $this->assertInstanceOf(PageReference::class, $reference);
        $this->assertSame('users', $reference->page);
        $this->assertSame('{id}/edit', $reference->suffix);
        $this->assertSame('@page:users/{id}/edit', (string) $reference);
    }

    public function testAnActionReferenceCarriesOnlyTheVerb(): void
    {
        $reference = $this->resolve('@action:archive');

        $this->assertInstanceOf(ActionReference::class, $reference);
        $this->assertSame('archive', $reference->action);
        $this->assertSame('@action:archive', (string) $reference);
    }

    public function testAReferenceSurvivesTheConfigurationCache(): void
    {
        // var_export() is how a compiled page file is written, so a reference
        // that cannot be read back is a reference that works only until the
        // cache is warmed.
        $page = eval('return ' . var_export(new PageReference('users', '{id}/edit'), true) . ';');
        $action = eval('return ' . var_export(new ActionReference('archive'), true) . ';');

        $this->assertInstanceOf(PageReference::class, $page);
        $this->assertSame('@page:users/{id}/edit', (string) $page);
        $this->assertInstanceOf(ActionReference::class, $action);
        $this->assertSame('@action:archive', (string) $action);
    }

    public function testAReferenceIsFoundInsideANestedArray(): void
    {
        $resolver = new Resolver(static fn (string $name): ?string => null);

        $resolved = $resolver->resolve([
            'actions' => ['edit' => ['to' => '@page:ads/{id}/edit']],
        ]);

        $actions = $resolved['actions'];
        $this->assertIsArray($actions);
        $edit = $actions['edit'];
        $this->assertIsArray($edit);
        $this->assertInstanceOf(PageReference::class, $edit['to']);
    }

    public function testARegionOfThisPageCarriesNoPage(): void
    {
        // Null, not the current page's name. The resolver does not know which
        // page it is resolving -- it is handed an array -- and inventing one
        // here is how a reference comes to address the wrong page's region
        // when the same shared definition is read from two page files.
        $reference = $this->resolve('@region:detail');

        $this->assertInstanceOf(RegionReference::class, $reference);
        $this->assertSame('detail', $reference->region);
        $this->assertNull($reference->page);
        $this->assertSame('@region:detail', (string) $reference);
    }

    public function testARegionOfAnotherPageCarriesBoth(): void
    {
        $reference = $this->resolve('@region:users.detail');

        $this->assertInstanceOf(RegionReference::class, $reference);
        $this->assertSame('users', $reference->page);
        $this->assertSame('detail', $reference->region);
        $this->assertSame('@region:users.detail', (string) $reference);
    }

    public function testASecondDotSaysWhichTwoPartsThereAre(): void
    {
        // `a.b.c` is not a deeper path, it is a mistake, and the syntax has
        // exactly two parts. Saying which two is more use than refusing the
        // whole string as unparseable.
        try {
            $this->resolve('@region:users.detail.rows');
            $this->fail('A three-part region reference was accepted.');
        } catch (ConfigException $e) {
            $this->assertStringContainsString('more than one dot', $e->getMessage());
            $this->assertStringContainsString('@region:users.detail', $e->getMessage());
        }
    }

    public function testAMalformedRegionReferenceIsRefused(): void
    {
        foreach (['@region:Detail', '@region:users.', '@region:.detail', '@region:a-b'] as $value) {
            try {
                $this->resolve($value);
                $this->fail("Expected {$value} to be refused.");
            } catch (ConfigException $e) {
                $this->assertStringContainsString($value, $e->getMessage());
            }
        }
    }

    /**
     * Every one of these starts with a reference namespace, so none of them can
     * be allowed to fall through as ordinary text: a `to` carrying the literal
     * string '@page:' would be a link to nowhere that nothing ever diagnosed.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function refusedReferences(): iterable
    {
        yield 'no key at all' => ['@page:', 'names no page'];
        yield 'no action' => ['@action:', 'names no action'];
        yield 'trailing slash' => ['@page:ads/', 'not a valid page reference'];
        yield 'double slash' => ['@page:ads//edit', 'not a valid page reference'];
        yield 'traversal' => ['@page:ads/../users', 'not a valid page reference'];
        yield 'leading slash' => ['@page:/ads', 'not a valid page reference'];
        yield 'upper-case namespace' => ['@Page:ads', 'lower case'];
        yield 'upper-case page' => ['@page:Ads', 'not a valid page reference'];
        yield 'numeric page' => ['@page:42', 'not a valid page reference'];
        yield 'upper-case action' => ['@action:Archive', 'not a valid action reference'];
        yield 'action with a path' => ['@action:ads/archive', 'not a valid action reference'];
        yield 'action with a placeholder' => ['@action:archive/{id}', 'not a valid action reference'];
        yield 'page as a dotted path' => ['@page:users.detail', "that is '@region:'"];
        yield 'query string' => ['@page:ads?sort=title', 'not a valid page reference'];
        yield 'fragment' => ['@page:ads#top', 'not a valid page reference'];
        yield 'space' => ['@page:ads /edit', 'not a valid page reference'];
        yield 'percent escape' => ['@page:%2E%2E/ads', 'not a valid page reference'];
        yield 'unbalanced brace' => ['@page:ads/{id/edit', 'not a valid page reference'];
        yield 'host' => ['@page://evil.example/x', 'not a valid page reference'];
    }

    #[DataProvider('refusedReferences')]
    public function testAMalformedReferenceIsRefusedRatherThanLeftAsText(string $value, string $expected): void
    {
        try {
            $this->resolve($value);
            $this->fail("Expected '{$value}' to be refused rather than left as text.");
        } catch (ConfigException $e) {
            $this->assertStringContainsString($value, $e->getMessage());
            $this->assertStringContainsString($expected, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function acceptedReferences(): iterable
    {
        yield 'plain page' => ['@page:ads', 'ads', ''];
        yield 'snake case page' => ['@page:ad_groups', 'ad_groups', ''];
        yield 'digits' => ['@page:log2', 'log2', ''];
        yield 'create form' => ['@page:ads/create', 'ads', 'create'];
        yield 'row' => ['@page:ads/{id}', 'ads', '{id}'];
        yield 'row placeholder with an underscore' => ['@page:users/{user_id}/edit', 'users', '{user_id}/edit'];
        yield 'a literal row id' => ['@page:settings/1/edit', 'settings', '1/edit'];
    }

    #[DataProvider('acceptedReferences')]
    public function testAWellFormedPageReferenceIsAccepted(string $value, string $page, string $suffix): void
    {
        $reference = $this->resolve($value);

        $this->assertInstanceOf(PageReference::class, $reference);
        $this->assertSame($page, $reference->page);
        $this->assertSame($suffix, $reference->suffix);
    }

    public function testAnUnknownNamespaceIsStillOrdinaryText(): void
    {
        // Only the three namespaces 8.5 defines are claimed. '@all' is a real
        // configuration value a preview region writes, and an email address in
        // a label must stay what it is.
        $this->assertSame('@all', $this->resolve('@all'));
        $this->assertSame('@pages:ads', $this->resolve('@pages:ads'));
        $this->assertSame('tomas@page:ads', $this->resolve('tomas@page:ads'));
    }

    public function testAnEnumReferenceStillResolvesTheWayItDid(): void
    {
        $enum = $this->resolve('@enum:ad_state');

        $this->assertInstanceOf(EnumReference::class, $enum);
        $this->assertSame('@enum:ad_state', (string) $enum);
    }
}
