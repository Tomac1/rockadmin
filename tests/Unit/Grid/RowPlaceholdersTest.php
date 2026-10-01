<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Grid;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Config\ActionReference;
use RockAdmin\Config\PageReference;
use RockAdmin\Grid\RowPlaceholders;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\Http\Router;
use RockAdmin\Http\UrlGenerator;

#[CoversClass(RowPlaceholders::class)]
final class RowPlaceholdersTest extends TestCase
{
    private const string BASE = '/admin';

    // ---------------------------------------------------------------- happy path

    public function testSubstitutesAColumnOfTheRow(): void
    {
        $reference = RowPlaceholders::reference(
            new PageReference('ads', '{id}/edit'),
            ['id' => 42, 'title' => 'Hello'],
        );

        self::assertSame('ads', $reference->page);
        self::assertSame('42/edit', $reference->suffix);
    }

    public function testAReferenceWithoutAPlaceholderIsReturnedUnchanged(): void
    {
        $reference = new PageReference('ads', 'create');

        self::assertSame($reference, RowPlaceholders::reference($reference, []));
    }

    public function testAnActionReferenceIsReturnedUnchanged(): void
    {
        $reference = new ActionReference('archive');

        self::assertSame($reference, RowPlaceholders::reference($reference, ['id' => 1]));
    }

    public function testSeveralPlaceholdersInOneSegmentConcatenate(): void
    {
        $reference = RowPlaceholders::reference(
            new PageReference('orders', '{year}-{month}/edit'),
            ['year' => '2026', 'month' => '10'],
        );

        self::assertSame('2026-10/edit', $reference->suffix);
    }

    public function testAnIntegerAndAFloatAreAccepted(): void
    {
        self::assertSame(
            '42/0.5',
            RowPlaceholders::reference(
                new PageReference('ads', '{id}/{ratio}'),
                ['id' => 42, 'ratio' => 0.5],
            )->suffix,
        );
    }

    // ------------------------------------------------- the test that matters

    /**
     * A hostile row value must still address its own row.
     *
     * Asserted by resolving the produced URL the way the stack really does —
     * the host decodes the path exactly once, `Request` normalises it, `Router`
     * matches it without decoding again — and reading the `id` back out. Both
     * URL modes are resolved, because the two apply their single encoding layer
     * in different places and a value that survives one must survive the other.
     */
    #[DataProvider('hostileValues')]
    public function testAHostileValueStillAddressesItsOwnRow(string $value): void
    {
        $reference = RowPlaceholders::reference(new PageReference('ads', '{id}/edit'), ['id' => $value]);

        foreach ([UrlGenerator::MODE_PATH, UrlGenerator::MODE_QUERY] as $mode) {
            $url = (new UrlGenerator(self::BASE, $mode))->reference($reference);
            $route = $this->resolve($url, $mode);

            self::assertNotNull($route, "{$mode} mode produced an unroutable URL: {$url}");
            self::assertSame('page.edit', $route->name, "wrong route in {$mode} mode: {$url}");
            self::assertSame('ads', $route->param('page'));
            self::assertSame($value, $route->param('id'), "wrong row in {$mode} mode: {$url}");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function hostileValues(): iterable
    {
        yield 'question mark' => ['a?b'];
        yield 'hash' => ['a#b'];
        yield 'percent' => ['50%'];
        yield 'already encoded slash' => ['a%2Fb'];
        yield 'space' => ['a b'];
        yield 'newline' => ["a\nb"];
        yield 'backslash' => ['a\\b'];                  // one literal backslash
        yield 'windows traversal' => ['..\\..\\etc'];   // backslashes, no separator
        yield 'plus' => ['a+b'];
        yield 'ampersand' => ['a&b=c'];
        yield 'semicolon' => ['a;b'];
        yield 'equals sign' => ['a=b'];
        yield 'quotes' => ['a"b\'c'];
        yield 'angle brackets' => ['<script>'];
        yield 'colon' => ['a:b'];
        yield 'at sign' => ['@page:ads'];
        yield 'leading dash' => ['-rf'];
        yield 'one dot' => ['a.b'];
        yield 'two dots inside' => ['a..b'];
        yield 'unicode' => ['ěščřž'];
        yield 'nul-ish' => ['a%00b'];
    }

    /**
     * A row value containing a brace is refused, and this is the seam between
     * this class and `UrlGenerator::reference()`. The value is substituted
     * *raw* — the single encoding layer is `UrlGenerator::build()`, which knows
     * the mode — so a value of `{id}` would reach that refusal looking exactly
     * like a placeholder nobody substituted, and be rejected a layer later with
     * a message blaming the template instead of the data. Refusing it here says
     * which row and which column.
     */
    #[DataProvider('bracedValues')]
    public function testABraceInTheValueIsRefusedWhereItCanStillBeExplained(string $value): void
    {
        $message = $this->refusal(
            static fn () => RowPlaceholders::reference(new PageReference('ads', '{id}/edit'), ['id' => $value]),
        );

        self::assertStringContainsString('brace', $message);
        self::assertStringContainsString("'id'", $message);
    }

    /** @return iterable<string, array{string}> */
    public static function bracedValues(): iterable
    {
        yield 'a whole placeholder' => ['{id}'];
        yield 'an opening brace' => ['a{b'];
        yield 'a closing brace' => ['a}b'];
    }

    // ------------------------------------------------------- loud refusals

    public function testAKeyTheRowDoesNotCarryIsRefusedNamingWhatItDoesCarry(): void
    {
        $message = $this->refusal(
            static fn () => RowPlaceholders::reference(
                new PageReference('ads', '{user_id}/edit'),
                ['id' => 1, 'title' => 'Hello'],
            ),
        );

        self::assertStringContainsString('{user_id}', $message);
        self::assertStringContainsString('@page:ads/{user_id}/edit', $message);
        self::assertStringContainsString('id, title', $message);
    }

    public function testAnEmptyRowRefusesRatherThanProducingADoubleSlash(): void
    {
        $message = $this->refusal(
            static fn () => RowPlaceholders::reference(new PageReference('ads', '{id}/edit'), []),
        );

        self::assertStringContainsString('{id}', $message);
        self::assertStringContainsString('no columns', $message);
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('refusedValues')]
    public function testAValueThatCannotAddressARowIsRefused(array $row, string $expected): void
    {
        $message = $this->refusal(
            static fn () => RowPlaceholders::reference(new PageReference('ads', '{id}/edit'), $row),
        );

        self::assertStringContainsString($expected, $message);
        self::assertStringContainsString("'id'", $message);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function refusedValues(): iterable
    {
        yield 'null' => [['id' => null], 'NULL'];
        yield 'empty string' => [['id' => ''], 'empty'];
        yield 'array' => [['id' => [1, 2]], 'array'];
        yield 'object' => [['id' => new \stdClass()], 'stdClass'];
        yield 'true' => [['id' => true], 'boolean'];
        yield 'false' => [['id' => false], 'boolean'];
        yield 'slash' => [['id' => 'a/b'], 'separator'];
        yield 'traversal' => [['id' => '../../etc/passwd'], 'separator'];
        yield 'one dot' => [['id' => '.'], 'dot segment'];
        yield 'two dots' => [['id' => '..'], 'dot segment'];
    }

    /**
     * The value the brief calls the dangerous one, and the one decision this
     * class takes against the brief's wording: `../../etc/passwd` is refused
     * rather than encoded, because no encoding of it can address its own row.
     * The host decodes a path once and `Request::normalizePath()` then drops
     * every dot segment, so a single layer resolves to `/p/ads/etc/passwd/edit`
     * and a second layer leaves `%2F` in the id, which is not the row's key.
     * `UrlGenerator::route()` refuses a `/` in a parameter for exactly this
     * reason, and every row link a grid draws today goes through it.
     */
    public function testTraversalProducesNoUrlAtAll(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RowPlaceholders::reference(
            new PageReference('ads', '{id}/edit'),
            ['id' => '../../etc/passwd'],
        );
    }

    // ------------------------------------------- the other kind of placeholder

    /**
     * `{{user.id}}` is the configuration placeholder, resolved once at load
     * from the signed-in administrator. It is not resolved here — it is
     * refused here, by name, because a string still carrying one at render
     * time means it was written where a row placeholder was meant, and that
     * mistake puts the administrator's own id on every row.
     */
    public function testAConfigurationPlaceholderIsRefusedNotResolved(): void
    {
        $message = $this->refusal(
            static fn () => RowPlaceholders::reference(
                new PageReference('ads', '{{user.id}}/edit'),
                ['user_id' => 7, 'id' => 1],
            ),
        );

        self::assertStringContainsString('{{user.id}}', $message);
        self::assertStringContainsString('{user_id}', $message);
        self::assertStringNotContainsString('7', $message);
    }

    #[DataProvider('malformedPlaceholders')]
    public function testAMalformedPlaceholderIsRefused(string $suffix): void
    {
        $message = $this->refusal(
            static fn () => RowPlaceholders::reference(
                new PageReference('ads', $suffix),
                ['id' => 1, 'Id' => 2, 'user_id' => 3],
            ),
        );

        self::assertStringContainsString('row placeholder', $message);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedPlaceholders(): iterable
    {
        yield 'upper case' => ['{Id}/edit'];
        yield 'leading digit' => ['{1id}/edit'];
        yield 'leading underscore' => ['{_id}/edit'];
        yield 'dash' => ['{user-id}/edit'];
        yield 'dot' => ['{user.id}/edit'];
        yield 'space' => ['{user id}/edit'];
        yield 'empty' => ['{}/edit'];
        yield 'nested' => ['{{id}/edit'];
    }

    /**
     * The two refusals must not disagree about what "unsubstituted" means.
     * `UrlGenerator::reference()` refuses any `/\{[^}]*}/` in the path it is
     * handed — the pattern is repeated here deliberately, because the point of
     * the test is that the two agree — and this class runs before it. So
     * nothing this class returns may still carry that sequence.
     */
    public function testNothingThisClassReturnsIsStillUnsubstitutedDownstream(): void
    {
        $accepted = [
            'one placeholder' => ['{id}/edit', ['id' => 42]],
            'two in a segment' => ['{year}-{month}', ['year' => 2026, 'month' => 10]],
            'two segments' => ['{id}/{tab}', ['id' => 42, 'tab' => 'media']],
            'a hostile value' => ['{id}/edit', ['id' => "a?#% \n" . '\\']],
            'an unmatched brace' => ['a{b', []],
            'no placeholder' => ['create', []],
        ];

        $urls = new UrlGenerator(self::BASE);

        foreach ($accepted as $label => [$suffix, $row]) {
            $result = RowPlaceholders::reference(new PageReference('ads', $suffix), $row);

            self::assertSame(0, preg_match('/\{[^}]*}/', $result->suffix), $label);
            self::assertNotSame('', $urls->reference($result), $label);
        }
    }

    // ------------------------------------------------------------- helpers

    /** @param callable(): mixed $call */
    private function refusal(callable $call): string
    {
        try {
            $call();
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        self::fail('Expected the substitution to be refused.');
    }

    /**
     * Resolves a generated URL the way the stack does: the host decodes the
     * path exactly once, and nothing decodes afterwards.
     */
    private function resolve(string $url, string $mode): ?Route
    {
        if ($mode === UrlGenerator::MODE_QUERY) {
            self::assertStringStartsWith(self::BASE . '?', $url);

            parse_str(substr($url, \strlen(self::BASE) + 1), $query);

            $path = \is_string($query['ra'] ?? null) ? $query['ra'] : '';
        } else {
            self::assertStringStartsWith(self::BASE . '/', $url);

            $path = rawurldecode(substr($url, \strlen(self::BASE)));
        }

        return (new Router())->match(new Request('GET', $path));
    }
}
