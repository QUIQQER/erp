<?php

namespace QUITests\ERP\Database;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use QUI\ERP\Database\ManufacturerSearch;
use QUITests\ERP\DatabaseTestCase;

use function strtotime;

class ManufacturerSearchTest extends DatabaseTestCase
{
    private string $usersTable;
    private string $addressesTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersTable = $this->testTableName('users');
        $UsersTable = new Table($this->usersTable);
        $UsersTable->addColumn('id', Types::INTEGER);
        $UsersTable->addColumn('firstname', Types::STRING, ['length' => 255, 'notnull' => false]);
        $UsersTable->addColumn('lastname', Types::STRING, ['length' => 255, 'notnull' => false]);
        $UsersTable->addColumn('email', Types::STRING, ['length' => 255, 'notnull' => false]);
        $UsersTable->addColumn('username', Types::STRING, ['length' => 255]);
        $UsersTable->addColumn('usergroup', Types::TEXT, ['notnull' => false]);
        $UsersTable->addColumn('active', Types::INTEGER);
        $UsersTable->addColumn('regdate', Types::INTEGER);
        $UsersTable->addColumn('address', Types::INTEGER, ['notnull' => false]);
        $UsersTable->setPrimaryKey(['id']);
        $this->createTestTable($UsersTable);

        $this->addressesTable = $this->testTableName('addresses');
        $AddressesTable = new Table($this->addressesTable);
        $AddressesTable->addColumn('id', Types::INTEGER);
        $AddressesTable->addColumn('company', Types::STRING, ['length' => 255]);
        $AddressesTable->setPrimaryKey(['id']);
        $this->createTestTable($AddressesTable);

        $this->Connection->insert($this->addressesTable, ['id' => 1, 'company' => 'Acme GmbH']);
        $this->Connection->insert($this->addressesTable, ['id' => 2, 'company' => 'Beta AG']);
        $this->insertUser(1, 'Anna', 'Alpha', 'anna@example.test', 'alpha', ',10,', '2026-01-10', 1);
        $this->insertUser(2, 'Bert', 'Beta', 'bert@example.test', 'beta', ',20,', '2026-02-10', 2);
        $this->insertUser(3, 'Carla', 'Gamma', 'carla@example.test', 'gamma', ',30,', '2026-03-10', null);
        $this->insertUser(4, 'Dora', 'Delta', 'dora@example.test', 'delta', ',10,20,', '2026-04-10', null);
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
        $this->assertSame(
            4,
            (int)$this->Connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($this->usersTable)
            )
        );
    }

    public function testInvalidSortColumnIsNotUsedAsSql(): void
    {
        $rows = $this->search(
            [10, 20, 30],
            ['sortOn' => 'id; DROP TABLE users'],
            ['limit' => '0,20']
        );

        $this->assertCount(4, $rows);
        $this->assertSame(
            4,
            (int)$this->Connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($this->usersTable)
            )
        );
    }

    public function testSearchValueIsBoundAsParameter(): void
    {
        $rows = $this->search([10, 20, 30], ['search' => "%' OR 1=1 --"]);

        $this->assertSame([], $rows);
        $this->assertSame(
            4,
            (int)$this->Connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($this->usersTable)
            )
        );
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
            $this->usersTable,
            $this->addressesTable,
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
        $this->Connection->insert($this->usersTable, [
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
