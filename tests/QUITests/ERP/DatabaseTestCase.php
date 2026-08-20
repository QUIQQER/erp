<?php

declare(strict_types=1);

namespace QUITests\ERP;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use QUI;

abstract class DatabaseTestCase extends TestCase
{
    protected Connection $Connection;

    /** @var list<string> */
    private array $testTables = [];
    private bool $ownsConnection = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DatabaseEnvironment::usesCiDatabase()) {
            $this->Connection = QUI::getDataBaseConnection();
            return;
        }

        $this->Connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);
        $this->ownsConnection = true;
    }

    protected function tearDown(): void
    {
        try {
            $SchemaManager = $this->Connection->createSchemaManager();

            foreach (array_reverse($this->testTables) as $tableName) {
                if ($SchemaManager->tablesExist([$tableName])) {
                    $SchemaManager->dropTable($tableName);
                }
            }
        } finally {
            if ($this->ownsConnection) {
                $this->Connection->close();
            }

            parent::tearDown();
        }
    }

    protected function createTestTable(Table $Table): void
    {
        $this->Connection->createSchemaManager()->createTable($Table);
        $this->testTables[] = $Table->getName();
    }

    protected function testTableName(string $suffix): string
    {
        return 'erp_phpunit_' . bin2hex(random_bytes(6)) . '_' . $suffix;
    }
}
