<?php

namespace QUITests\ERP;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use QUI\ERP\Process;
use ReflectionMethod;

class ProcessDatabaseTest extends DatabaseTestCase
{
    private string $historyTable;
    private string $entriesTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->historyTable = $this->testTableName('history');
        $HistoryTable = new Table($this->historyTable);
        $HistoryTable->addColumn('id', Types::STRING, ['length' => 250]);
        $HistoryTable->addColumn('history', Types::TEXT, ['notnull' => false]);
        $HistoryTable->setPrimaryKey(['id']);
        $this->createTestTable($HistoryTable);

        $this->entriesTable = $this->testTableName('entries');
        $EntriesTable = new Table($this->entriesTable);
        $EntriesTable->addColumn('id', Types::INTEGER);
        $EntriesTable->addColumn('hash', Types::STRING, ['length' => 250]);
        $EntriesTable->addColumn('global_process_id', Types::STRING, ['length' => 250]);
        $EntriesTable->addColumn('date', Types::STRING, ['length' => 50]);
        $EntriesTable->setPrimaryKey(['id']);
        $this->createTestTable($EntriesTable);
    }

    public function testGetHistoryCreatesMissingProcessRow(): void
    {
        $Process = $this->createProcess('new-process');

        $this->assertTrue($Process->getHistory()->isEmpty());
        $this->assertSame(
            1,
            (int)$this->Connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($this->historyTable) . ' WHERE id = ?',
                ['new-process']
            )
        );
    }

    public function testGetHistoryLoadsStoredJson(): void
    {
        $this->Connection->insert($this->historyTable, [
            'id' => 'stored-process',
            'history' => json_encode([[
                'message' => 'Stored message',
                'time' => 1234567890,
                'id' => 'comment-id'
            ]])
        ]);

        $comments = $this->createProcess('stored-process')->getHistory()->toArray();

        $this->assertCount(1, $comments);
        $this->assertSame('Stored message', $comments[0]['message']);
        $this->assertSame(1234567890, $comments[0]['time']);
    }

    public function testAddHistoryUpdatesPersistedJson(): void
    {
        $Process = $this->createProcess('updated-process');
        $Process->addHistory('Persist me', 1234567890);

        $stored = $this->Connection->fetchOne(
            'SELECT history FROM ' . $this->Connection->quoteIdentifier($this->historyTable) . ' WHERE id = ?',
            ['updated-process']
        );
        $comments = json_decode((string)$stored, true);

        $this->assertIsArray($comments);
        $this->assertCount(1, $comments);
        $this->assertSame('Persist me', $comments[0]['message']);
        $this->assertSame(1234567890, $comments[0]['time']);
    }

    public function testRepeatedAddHistoryKeepsExistingComments(): void
    {
        $Process = $this->createProcess('repeated-process');
        $Process->addHistory('First', 100);
        $Process->addHistory('Second', 200);

        $stored = $this->Connection->fetchOne(
            'SELECT history FROM ' . $this->Connection->quoteIdentifier($this->historyTable) . ' WHERE id = ?',
            ['repeated-process']
        );
        $comments = json_decode((string)$stored, true);

        $this->assertSame(['First', 'Second'], array_column($comments, 'message'));
    }

    public function testProcessIdIsBoundWhenHistoryIsLoaded(): void
    {
        $Process = $this->createProcess("missing' OR 1=1 --");

        $this->assertTrue($Process->getHistory()->isEmpty());
        $this->assertSame(
            1,
            (int)$this->Connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($this->historyTable)
            )
        );
    }

    public function testProcessEntryQueryUsesOrSemantics(): void
    {
        $this->Connection->insert($this->entriesTable, [
            'id' => 1,
            'hash' => 'own-hash',
            'global_process_id' => 'central-process',
            'date' => '2026-01-01'
        ]);
        $this->Connection->insert($this->entriesTable, [
            'id' => 2,
            'hash' => 'central-process',
            'global_process_id' => 'other-process',
            'date' => '2026-01-02'
        ]);
        $this->Connection->insert($this->entriesTable, [
            'id' => 3,
            'hash' => 'unrelated',
            'global_process_id' => 'other-process',
            'date' => '2026-01-03'
        ]);

        $Method = new ReflectionMethod(Process::class, 'fetchProcessEntriesByProcessIdOrIdentifier');
        $rows = $Method->invoke(
            $this->createProcess('central-process'),
            $this->entriesTable,
            ['id', 'hash', 'global_process_id', 'date']
        );

        $this->assertSame([1, 2], array_column($rows, 'id'));
    }

    public function testDatabaseErrorsAreHandledForHistoryRead(): void
    {
        $this->Connection->createSchemaManager()->dropTable($this->historyTable);

        $this->assertTrue($this->createProcess('broken-process')->getHistory()->isEmpty());
    }

    private function createProcess(string $processId): Process
    {
        return new class ($processId, $this->Connection, $this->historyTable) extends Process {
            public function __construct(
                string $processId,
                private Connection $Connection,
                private string $historyTable
            ) {
                parent::__construct($processId);
            }

            protected function getDatabaseConnection(): Connection
            {
                return $this->Connection;
            }

            protected function table(): string
            {
                return $this->historyTable;
            }
        };
    }
}
