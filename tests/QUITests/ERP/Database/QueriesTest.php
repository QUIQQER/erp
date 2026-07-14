<?php

namespace QUITests\ERP\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Database\Queries;

class QueriesTest extends TestCase
{
    private Connection $Connection;

    protected function setUp(): void
    {
        $this->Connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->Connection->executeStatement(
            'CREATE TABLE process_entries ('
            . 'id INTEGER PRIMARY KEY, hash TEXT, global_process_id TEXT, date TEXT, ignored TEXT)'
        );

        $this->Connection->insert('process_entries', [
            'id' => 1,
            'hash' => 'own-hash',
            'global_process_id' => 'process-a',
            'date' => '2026-01-01',
            'ignored' => 'not-selected'
        ]);
        $this->Connection->insert('process_entries', [
            'id' => 2,
            'hash' => 'process-a',
            'global_process_id' => 'process-b',
            'date' => '2026-01-02',
            'ignored' => 'not-selected'
        ]);
        $this->Connection->insert('process_entries', [
            'id' => 3,
            'hash' => 'unrelated',
            'global_process_id' => 'process-c',
            'date' => '2026-01-03',
            'ignored' => 'not-selected'
        ]);
    }

    protected function tearDown(): void
    {
        $this->Connection->close();
    }

    public function testFetchAllAssociativeReturnsOnlyRequestedColumns(): void
    {
        $rows = Queries::fetchAllAssociative($this->Connection, 'process_entries', ['id', 'hash']);

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
        $this->assertSame(3, (int)$this->Connection->fetchOne('SELECT COUNT(*) FROM process_entries'));
    }

    public function testEitherIdentifierSupportsBookingColumnNames(): void
    {
        $this->Connection->executeStatement(
            'CREATE TABLE bookings (uuid TEXT, globalProcessId TEXT, createDate TEXT)'
        );
        $this->Connection->insert('bookings', [
            'uuid' => 'booking-id',
            'globalProcessId' => 'central-process',
            'createDate' => '2026-05-01'
        ]);

        $rows = Queries::fetchAllAssociativeByEitherIdentifier(
            $this->Connection,
            'bookings',
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
            'process_entries',
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
            'process_entries',
            'hash',
            'missing'
        ));
    }

    public function testInsertAndUpdatePersistData(): void
    {
        $affected = Queries::insert($this->Connection, 'process_entries', [
            'id' => 4,
            'hash' => 'inserted',
            'global_process_id' => 'process-d',
            'date' => '2026-01-04'
        ]);
        $updated = Queries::update(
            $this->Connection,
            'process_entries',
            ['date' => '2026-02-01'],
            ['id' => 4]
        );

        $this->assertSame(1, $affected);
        $this->assertSame(1, $updated);
        $this->assertSame(
            '2026-02-01',
            $this->Connection->fetchOne('SELECT date FROM process_entries WHERE id = 4')
        );
    }

    public function testInvalidTableRaisesDbalException(): void
    {
        $this->expectException(DbalException::class);

        Queries::fetchAllAssociative($this->Connection, 'missing_table', ['id']);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function fetchByEitherIdentifier(string $value): array
    {
        return Queries::fetchAllAssociativeByEitherIdentifier(
            $this->Connection,
            'process_entries',
            ['id', 'hash', 'global_process_id'],
            'global_process_id',
            'hash',
            $value
        );
    }
}
