<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\ErpTransactionsInterface;
use QUI\ERP\Process;

class ProcessGroupingTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $stubFiles = [
            'QUI/ERP/Accounting/Invoice/Invoice.php',
            'QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
            'QUI/ERP/Order/AbstractOrder.php',
            'QUI/ERP/Order/Order.php',
            'QUI/ERP/SalesOrders/SalesOrder.php'
        ];

        foreach ($stubFiles as $stubFile) {
            require_once dirname(__DIR__, 2) . '/stubs/' . $stubFile;
        }
    }

    protected function createTransactionalEntityMock(string $uuid, array $payload): object
    {
        $Entity = $this->createMockForIntersectionOfInterfaces([
            ErpEntityInterface::class,
            ErpTransactionsInterface::class
        ]);

        $Entity->method('getUUID')->willReturn($uuid);
        $Entity->method('toArray')->willReturn($payload);

        return $Entity;
    }

    public function testGroupedRelatedTransactionEntitiesWithoutKnownEntityClasses(): void
    {
        $EntityA = $this->createTransactionalEntityMock('a', ['uuid' => 'a']);
        $EntityB = $this->createTransactionalEntityMock('b', ['uuid' => 'b']);

        $entities = [$EntityA, $EntityB];
        $Process = new class ('process-group-test', $entities) extends Process {
            protected array $entities = [];

            public function __construct(string $processId, array $entities)
            {
                parent::__construct($processId);
                $this->entities = $entities;
            }

            public function getEntities(): array
            {
                return $this->entities;
            }
        };

        $result = $Process->getGroupedRelatedTransactionEntities();

        $this->assertCount(2, $result['entities']);
        $this->assertSame([], $result['grouped']);
        $this->assertCount(2, $result['notGroup']);
        $this->assertSame('a', $result['entities'][0]['uuid']);
        $this->assertSame('b', $result['entities'][1]['uuid']);
    }

    public function testGroupedRelatedTransactionEntitiesWithFilter(): void
    {
        $EntityA = $this->createTransactionalEntityMock('a', ['uuid' => 'a']);
        $EntityB = $this->createTransactionalEntityMock('b', ['uuid' => 'b']);

        $entities = [$EntityA, $EntityB];
        $Process = new class ('process-group-test', $entities) extends Process {
            protected array $entities = [];

            public function __construct(string $processId, array $entities)
            {
                parent::__construct($processId);
                $this->entities = $entities;
            }

            public function getEntities(): array
            {
                return $this->entities;
            }
        };

        $result = $Process->getGroupedRelatedTransactionEntities(function ($Entity) {
            return $Entity->getUUID() === 'a';
        });

        $this->assertCount(1, $result['entities']);
        $this->assertCount(1, $result['notGroup']);
        $this->assertSame('a', $result['entities'][0]['uuid']);
    }
}
