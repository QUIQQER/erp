<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\ErpTransactionsInterface;
use QUI\ERP\Process;

class ProcessGroupingTestProcess extends Process
{
    protected array $entities = [];

    public function __construct(array $entities)
    {
        parent::__construct('process-group-test');
        $this->entities = $entities;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }
}

class ProcessGroupingTest extends TestCase
{
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

        $Process = new ProcessGroupingTestProcess([$EntityA, $EntityB]);
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

        $Process = new ProcessGroupingTestProcess([$EntityA, $EntityB]);
        $result = $Process->getGroupedRelatedTransactionEntities(function ($Entity) {
            return $Entity->getUUID() === 'a';
        });

        $this->assertCount(1, $result['entities']);
        $this->assertCount(1, $result['notGroup']);
        $this->assertSame('a', $result['entities'][0]['uuid']);
    }
}
