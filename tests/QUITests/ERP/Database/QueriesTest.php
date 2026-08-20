<?php

namespace QUITests\ERP\Database;

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use QUI\ERP\Database\Queries;
use QUITests\ERP\DatabaseTestCase;

class QueriesTest extends DatabaseTestCase
{
    private string $entriesTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entriesTable = $this->testTableName('entries');
        $Table = new Table($this->entriesTable);
        $Table->addColumn('id', Types::INTEGER);
        $Table->addColumn('hash', Types::STRING, ['length' => 250]);
        $Table->addColumn('global_process_id', Types::STRING, ['length' => 250]);
        $Table->addColumn('date', Types::STRING, ['length' => 50]);
        $Table->addColumn('ignored', Types::STRING, ['length' => 50, 'notnull' => false]);
        $Table->setPrimaryKey(['id']);
        $this->createTestTable($Table);

        $this->Connection->insert($this->entriesTable, [
            'id' => 1,
            'hash' => 'own-hash',
            'global_process_id' => 'process-a',
            'date' => '2026-01-01',
            'ignored' => 'not-selected'
        ]);
        $this->Connection->insert($this->entriesTable, [
            'id' => 2,
            'hash' => 'process-a',
            'global_process_id' => 'process-b',
            'date' => '2026-01-02',
            'ignored' => 'not-selected'
        ]);
        $this->Connection->insert($this->entriesTable, [
            'id' => 3,
            'hash' => 'unrelated',
            'global_process_id' => 'process-c',
            'date' => '2026-01-03',
            'ignored' => 'not-selected'
        ]);
    }

    public function testFetchAllAssociativeReturnsOnlyRequestedColumns(): void
    {
        $rows = Queries::fetchAllAssociative($this->Connection, $this->entriesTable, ['id', 'hash']);

        $this->assertCount(3, $rows);
        $this->assertSame(['id', 'hash'], array_keys($rows[0]));
        $this->assertSame('own-hash', $rows[0]['hash']);
        $this->assertArrayNotHasKey('ignored', $rows[0]);
    }

    public function testEitherIdentifierMatchesFirstIdentifier(): void
    {
        $rows = $this->fetchByEitherIdentifier('process-a');

        $this->assertSame([1, 2], array_column($rows, 'id'));
    }

    public function testEitherIdentifierMatchesSecondIdentifier(): void
    {
        $rows = $this->fetchByEitherIdentifier('own-hash');

        $this->assertSame([1], array_column($rows, 'id'));
    }

    public function testEitherIdentifierDoesNotRequireBothIdentifiersToMatch(): void
    {
        $rows = $this->fetchByEitherIdentifier('process-b');

        $this->assertSame([2], array_column($rows, 'id'));
    }

    public function testEitherIdentifierReturnsEmptyArrayWithoutMatch(): void
    {
        $this->assertSame([], $this->fetchByEitherIdentifier('missing'));
    }

    public function testEitherIdentifierBindsValueInsteadOfInterpolatingIt(): void
    {
        $this->assertSame([], $this->fetchByEitherIdentifier("process-a' OR 1=1 --"));
        $this->assertSame(
            3,
            (int)$this->Connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($this->entriesTable)
            )
        );
    }

    public function testEitherIdentifierSupportsBookingColumnNames(): void
    {
        $bookingsTable = $this->testTableName('bookings');
        $Table = new Table($bookingsTable);
        $Table->addColumn('uuid', Types::STRING, ['length' => 250]);
        $Table->addColumn('globalProcessId', Types::STRING, ['length' => 250]);
        $Table->addColumn('createDate', Types::STRING, ['length' => 50]);
        $this->createTestTable($Table);
        $this->Connection->insert($bookingsTable, [
            'uuid' => 'booking-id',
            'globalProcessId' => 'central-process',
            'createDate' => '2026-05-01'
        ]);

        $rows = Queries::fetchAllAssociativeByEitherIdentifier(
            $this->Connection,
            $bookingsTable,
            ['uuid', 'globalProcessId', 'createDate'],
            'globalProcessId',
            'uuid',
            'central-process'
        );

        $this->assertCount(1, $rows);
        $this->assertSame('booking-id', $rows[0]['uuid']);
    }

    public function testFetchAssociativeByIdentifierFindsOneRow(): void
    {
        $row = Queries::fetchAssociativeByIdentifier(
            $this->Connection,
            $this->entriesTable,
            'hash',
            'own-hash'
        );

        $this->assertIsArray($row);
        $this->assertSame(1, $row['id']);
    }

    public function testFetchAssociativeByIdentifierReturnsFalseWithoutMatch(): void
    {
        $this->assertFalse(Queries::fetchAssociativeByIdentifier(
            $this->Connection,
            $this->entriesTable,
            'hash',
            'missing'
        ));
    }

    public function testInsertAndUpdatePersistData(): void
    {
        $affected = Queries::insert($this->Connection, $this->entriesTable, [
            'id' => 4,
            'hash' => 'inserted',
            'global_process_id' => 'process-d',
            'date' => '2026-01-04'
        ]);
        $updated = Queries::update(
            $this->Connection,
            $this->entriesTable,
            ['date' => '2026-02-01'],
            ['id' => 4]
        );

        $this->assertSame(1, $affected);
        $this->assertSame(1, $updated);
        $this->assertSame(
            '2026-02-01',
            $this->Connection->fetchOne(
                'SELECT date FROM ' . $this->Connection->quoteIdentifier($this->entriesTable) . ' WHERE id = 4'
            )
        );
    }

    public function testInvalidTableRaisesDbalException(): void
    {
        $this->expectException(DbalException::class);

        Queries::fetchAllAssociative($this->Connection, $this->testTableName('missing'), ['id']);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function fetchByEitherIdentifier(string $value): array
    {
        return Queries::fetchAllAssociativeByEitherIdentifier(
            $this->Connection,
            $this->entriesTable,
            ['id', 'hash', 'global_process_id'],
            'global_process_id',
            'hash',
            $value
        );
    }
}
