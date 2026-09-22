<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Db\Dialect;
use RockAdmin\Db\MySqlDialect;
use RockAdmin\Db\PgDialect;
use RockAdmin\Db\Sql;

#[CoversClass(MySqlDialect::class)]
#[CoversClass(PgDialect::class)]
#[CoversClass(Sql::class)]
final class DialectTest extends TestCase
{
    public function testNames(): void
    {
        $this->assertSame('mysql', (new MySqlDialect())->name());
        $this->assertSame('pgsql', (new PgDialect())->name());
    }

    public function testIdentifierQuoting(): void
    {
        $this->assertSame('`ads`', (new MySqlDialect())->quoteIdentifier('ads'));
        $this->assertSame('"ads"', (new PgDialect())->quoteIdentifier('ads'));
    }

    public function testAQuoteInsideAnIdentifierIsDoubled(): void
    {
        // Identifiers come from configuration, not from a request, but an
        // unescaped quote would still turn a typo into broken SQL.
        $this->assertSame('`we``ird`', (new MySqlDialect())->quoteIdentifier('we`ird'));
        $this->assertSame('"we""ird"', (new PgDialect())->quoteIdentifier('we"ird'));
    }

    public function testQualifying(): void
    {
        $this->assertSame('`ads`.`title`', (new MySqlDialect())->qualify('ads', 'title'));
        $this->assertSame('"ads"."title"', (new PgDialect())->qualify('ads', 'title'));
    }

    public function testCaseInsensitiveLike(): void
    {
        $this->assertSame('LIKE', (new MySqlDialect())->caseInsensitiveLike());
        $this->assertSame('ILIKE', (new PgDialect())->caseInsensitiveLike());
    }

    public function testJsonPathBindsThePointerRatherThanInliningIt(): void
    {
        $mysql = (new MySqlDialect())->jsonPath('`ads`.`stats`', ['daily', 'views']);

        $this->assertStringContainsString('JSON_EXTRACT(`ads`.`stats`, ?)', $mysql->text);
        $this->assertSame(['$."daily"."views"'], $mysql->bindings);

        $pgsql = (new PgDialect())->jsonPath('"ads"."stats"', ['daily', 'views']);

        $this->assertStringContainsString('"ads"."stats" #>> ?', $pgsql->text);
        $this->assertSame(['{"daily","views"}'], $pgsql->bindings);
    }

    public function testJsonPathEscapesAKeyContainingQuotesBackslashesAndCommas(): void
    {
        // A key is data, not a delimiter: a comma, brace, quote or backslash
        // inside one must not silently change the path or break the syntax.
        $key = 'a"b\\c,d}e';

        $mysql = (new MySqlDialect())->jsonPath('`ads`.`stats`', [$key]);
        $this->assertSame(['$."a\\"b\\\\c,d}e"'], $mysql->bindings);

        $pgsql = (new PgDialect())->jsonPath('"ads"."stats"', [$key]);
        $this->assertSame(['{"a\\"b\\\\c,d}e"}'], $pgsql->bindings);
    }

    public function testEstimatedCountBindsTheTableName(): void
    {
        foreach ([new MySqlDialect(), new PgDialect()] as $dialect) {
            $sql = $dialect->estimatedCount('ads');

            $this->assertSame(['ads'], $sql->bindings, $dialect->name());
            $this->assertStringNotContainsString("'ads'", $sql->text, $dialect->name());
        }
    }

    public function testSqlCarriesItsBindings(): void
    {
        $sql = new Sql('SELECT 1 WHERE x = ?', [42]);

        $this->assertSame('SELECT 1 WHERE x = ?', $sql->text);
        $this->assertSame([42], $sql->bindings);
        $this->assertSame([], (new Sql('SELECT 1'))->bindings);
    }

    public function testEveryDialectImplementsTheInterface(): void
    {
        $this->assertInstanceOf(Dialect::class, new MySqlDialect());
        $this->assertInstanceOf(Dialect::class, new PgDialect());
    }
}
