<?php

namespace QUITests\ERP;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Process;
use ReflectionMethod;

class ProcessDatabaseTest extends TestCase
{
    private Connection $Connection;

    protected function setUp(): void
    {
        $this->Connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->Connection->executeStatement(
            'CREATE TABLE process_history (id TEXT PRIMARY KEY, history TEXT)'
        );
        $this->Connection->executeStatement(
            'CREATE TABLE process_entries ('
            . 'id INTEGER PRIMARY KEY, hash TEXT, global_process_id TEXT, date TEXT)'
        );
    }

    protected function tearDown(): void
    {
        $this->Connection->close();
    }

    public function testGetHistoryCreatesMissingProcessRow(): void
    {
        $Process = $this->createProcess('new-process');

        $this->assertTrue($Process->getHistory()->isEmpty());
        $this->assertSame(
            1,
            (int)$this->Connection->fetchOne(
                'SELECT COUNT(*) FROM process_history WHERE id = ?',
                ['new-process']
            )
        );
    }

    public function testGetHistoryLoadsStoredJson(): void
    {
        $this->Connection->insert('process_history', [
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
            'SELECT history FROM process_history WHERE id = ?',
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
            'SELECT history FROM process_history WHERE id = ?',
            ['repeated-process']
        );
        $comments = json_decode((string)$stored, true);

        $this->assertSame(['First', 'Second'], array_column($comments, 'message'));
    }

    public function testProcessIdIsBoundWhenHistoryIsLoaded(): void
    {
        $Process = $this->createProcess("missing' OR 1=1 --");

        $this->assertTrue($Process->getHistory()->isEmpty());
        $this->assertSame(1, (int)$this->Connection->fetchOne('SELECT COUNT(*) FROM process_history'));
    }

    public function testProcessEntryQueryUsesOrSemantics(): void
    {
        $this->Connection->insert('process_entries', [
            'id' => 1,
            'hash' => 'own-hash',
            'global_process_id' => 'central-process',
            'date' => '2026-01-01'
        ]);
        $this->Connection->insert('process_entries', [
            'id' => 2,
            'hash' => 'central-process',
            'global_process_id' => 'other-process',
            'date' => '2026-01-02'
        ]);
        $this->Connection->insert('process_entries', [
            'id' => 3,
            'hash' => 'unrelated',
            'global_process_id' => 'other-process',
            'date' => '2026-01-03'
        ]);

        $Method = new ReflectionMethod(Process::class, 'fetchProcessEntriesByProcessIdOrIdentifier');
        $rows = $Method->invoke(
            $this->createProcess('central-process'),
            'process_entries',
            ['id', 'hash', 'global_process_id', 'date']
        );

        $this->assertSame([1, 2], array_column($rows, 'id'));
    }

    public function testDatabaseErrorsAreHandledForHistoryRead(): void
    {
        $this->Connection->executeStatement('DROP TABLE process_history');

        $this->assertTrue($this->createProcess('broken-process')->getHistory()->isEmpty());
    }

    private function createProcess(string $processId): Process
    {
        return new class ($processId, $this->Connection) extends Process {
            public function __construct(string $processId, private Connection $Connection)
            {
                parent::__construct($processId);
            }

            protected function getDatabaseConnection(): Connection
            {
                return $this->Connection;
            }

            protected function table(): string
            {
                return 'process_history';
            }
        };
    }
}
