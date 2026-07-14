<?php

namespace QUITests\ERP\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Database\ManufacturerSearch;

use function strtotime;

class ManufacturerSearchTest extends TestCase
{
    private Connection $Connection;

    protected function setUp(): void
    {
        $this->Connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->Connection->executeStatement(
            'CREATE TABLE users ('
            . 'id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, username TEXT, '
            . 'usergroup TEXT, active INTEGER, regdate INTEGER, address INTEGER)'
        );
        $this->Connection->executeStatement(
            'CREATE TABLE users_address (id INTEGER PRIMARY KEY, company TEXT)'
        );

        $this->Connection->insert('users_address', ['id' => 1, 'company' => 'Acme GmbH']);
        $this->Connection->insert('users_address', ['id' => 2, 'company' => 'Beta AG']);
        $this->insertUser(1, 'Anna', 'Alpha', 'anna@example.test', 'alpha', ',10,', '2026-01-10', 1);
        $this->insertUser(2, 'Bert', 'Beta', 'bert@example.test', 'beta', ',20,', '2026-02-10', 2);
        $this->insertUser(3, 'Carla', 'Gamma', 'carla@example.test', 'gamma', ',30,', '2026-03-10', null);
        $this->insertUser(4, 'Dora', 'Delta', 'dora@example.test', 'delta', ',10,20,', '2026-04-10', null);
    }

    protected function tearDown(): void
    {
        $this->Connection->close();
    }

    public function testManufacturerGroupsAreCombinedWithOr(): void
    {
        $rows = $this->search([10, 20]);

        $this->assertSame([1, 2, 4], array_column($rows, 'id'));
    }

    public function testUsersOutsideManufacturerGroupsAreExcluded(): void
    {
        $rows = $this->search([10]);

        $this->assertSame([1, 4], array_column($rows, 'id'));
    }

    public function testEmptyManufacturerGroupListPreservesLegacyUnrestrictedSearch(): void
    {
        $rows = $this->search([]);

        $this->assertSame([1, 2, 3, 4], array_column($rows, 'id'));
    }

    public function testDefaultSearchMatchesJoinedCompany(): void
    {
        $rows = $this->search([10, 20], ['search' => 'Acme']);

        $this->assertSame([1], array_column($rows, 'id'));
        $this->assertSame('Acme GmbH', $rows[0]['company']);
    }

    public function testSelectedSearchFieldRestrictsSearch(): void
    {
        $rows = $this->search([10, 20], [
            'search' => 'Alpha',
            'filter' => ['firstname' => 0, 'lastname' => 1]
        ]);

        $this->assertSame([1], array_column($rows, 'id'));
    }

    public function testSearchAndManufacturerGroupFiltersAreCombinedWithAnd(): void
    {
        $rows = $this->search([10], ['search' => 'beta']);

        $this->assertSame([], $rows);
    }

    public function testRegistrationDateRangeIncludesWholeBoundaryDays(): void
    {
        $rows = $this->search([10, 20, 30], [
            'filter' => [
                'regdate_from' => '2026-02-10',
                'regdate_to' => '2026-03-10'
            ]
        ]);

        $this->assertSame([2, 3], array_column($rows, 'id'));
    }

    public function testCountIgnoresGridLimit(): void
    {
        $count = $this->search([10, 20], [], ['limit' => '0,1'], true);

        $this->assertSame(3, $count);
    }

    public function testSortingAndOffsetLimitAreApplied(): void
    {
        $rows = $this->search(
            [10, 20, 30],
            ['sortOn' => 'username', 'sortBy' => 'DESC'],
            ['limit' => '1,2']
        );

        $this->assertSame(['delta', 'beta'], array_column($rows, 'username'));
    }

    public function testInvalidSortDirectionFallsBackToAscending(): void
    {
        $rows = $this->search(
            [10, 20, 30],
            ['sortOn' => 'username', 'sortBy' => 'DROP TABLE users'],
            ['limit' => '0,20']
        );

        $this->assertSame(['alpha', 'beta', 'delta', 'gamma'], array_column($rows, 'username'));
        $this->assertSame(4, (int)$this->Connection->fetchOne('SELECT COUNT(*) FROM users'));
    }

    public function testInvalidSortColumnIsNotUsedAsSql(): void
    {
        $rows = $this->search(
            [10, 20, 30],
            ['sortOn' => 'id; DROP TABLE users'],
            ['limit' => '0,20']
        );

        $this->assertCount(4, $rows);
        $this->assertSame(4, (int)$this->Connection->fetchOne('SELECT COUNT(*) FROM users'));
    }

    public function testSearchValueIsBoundAsParameter(): void
    {
        $rows = $this->search([10, 20, 30], ['search' => "%' OR 1=1 --"]);

        $this->assertSame([], $rows);
        $this->assertSame(4, (int)$this->Connection->fetchOne('SELECT COUNT(*) FROM users'));
    }

    public function testLeftJoinKeepsManufacturerWithoutAddress(): void
    {
        $rows = $this->search([10], ['search' => 'delta']);

        $this->assertSame([4], array_column($rows, 'id'));
        $this->assertNull($rows[0]['company']);
    }

    public function testDefaultLimitReturnsAtMostTwentyRows(): void
    {
        for ($id = 5; $id <= 25; $id++) {
            $this->insertUser(
                $id,
                'First' . $id,
                'Last' . $id,
                'user' . $id . '@example.test',
                'user' . $id,
                ',10,',
                '2026-05-01',
                null
            );
        }

        $this->assertCount(20, $this->search([10]));
    }

    /**
     * @param int[] $groupIds
     * @param array<mixed> $searchParams
     * @param array<mixed> $gridParams
     * @return array<int, array<string, mixed>>|int
     */
    private function search(
        array $groupIds,
        array $searchParams = [],
        array $gridParams = [],
        bool $countOnly = false
    ): array|int {
        return ManufacturerSearch::execute(
            $this->Connection,
            'users',
            'users_address',
            $groupIds,
            $searchParams,
            $gridParams,
            $countOnly
        );
    }

    private function insertUser(
        int $id,
        string $firstname,
        string $lastname,
        string $email,
        string $username,
        string $usergroup,
        string $regdate,
        ?int $address
    ): void {
        $this->Connection->insert('users', [
            'id' => $id,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'username' => $username,
            'usergroup' => $usergroup,
            'active' => 1,
            'regdate' => strtotime($regdate),
            'address' => $address
        ]);
    }
}
