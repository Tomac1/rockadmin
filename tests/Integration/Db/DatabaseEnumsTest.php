<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Integration\Db;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RockAdmin\Config\Enums;
use RockAdmin\Db\Connection;
use RockAdmin\Db\Sql;
use RockAdmin\Tests\Support\DatabaseTestCase;

#[CoversClass(Enums::class)]
final class DatabaseEnumsTest extends DatabaseTestCase
{
    private function enums(Connection $connection): Enums
    {
        return Enums::fromConfig([
            'companies' => [
                'source' => [
                    'table' => 'ra_test_companies',
                    'value' => 'id',
                    'label' => 'name',
                    'order' => 'name',
                ],
            ],
        ])->withConnection($connection);
    }

    #[DataProvider('connections')]
    public function testOptionsComeFromTheTableInTheOrderAsked(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $options = $this->enums($connection)->options('companies');

        // PHP normalises a numeric string used as an array key to an int, so the
        // keys read back as 2 and 1, not '2' and '1' -- array_keys() cannot see
        // otherwise, even though EnumOption::$value stays a string. Reading the
        // options back by position rather than by that coerced key keeps this
        // assertion honest about ordering without fighting PHPStan over it.
        $this->assertSame([2, 1], array_keys($options), 'ordered by name: Moto, then Velo');

        $ordered = array_values($options);

        $this->assertSame('2', $ordered[0]->value);
        $this->assertSame('Moto a.s.', $ordered[0]->label);
        $this->assertSame('1', $ordered[1]->value);
        $this->assertSame('Velo s.r.o.', $ordered[1]->label);

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testTheTableIsReadOnceHoweverOftenTheOptionsAreAsked(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $this->createFixtures($connection);

        $enums = $this->enums($connection);
        $enums->options('companies');

        $connection->execute(new Sql('INSERT INTO ra_test_companies (id, name) VALUES (?, ?)', [3, 'Aaa']));

        $this->assertCount(
            2,
            $enums->options('companies'),
            'memoised for the request: a grid of fifty rows must not read the table fifty times',
        );

        $this->dropFixtures($connection);
    }

    #[DataProvider('connections')]
    public function testAStaticEnumerationStillWorksWithAConnectionAttached(?Connection $connection): void
    {
        $connection = $this->requireConnection($connection);

        $enums = Enums::fromConfig(['state' => ['active' => ['label' => 'Active']]])
            ->withConnection($connection);

        $this->assertSame('Active', $enums->options('state')['active']->label);
    }
}
