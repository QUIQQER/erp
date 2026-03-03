<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\ErpEntityData;

class ErpEntityDataTest extends TestCase
{
    public function testGetReferenceDataWithCurrentStatus(): void
    {
        $Entity = new class {
            use ErpEntityData;

            public function getUUID(): string
            {
                return 'uuid-1';
            }

            public function getPrefixedNumber(): string
            {
                return 'INV-1';
            }

            public function getGlobalProcessId(): string
            {
                return 'gpi-1';
            }

            public function getCurrentStatusId(): int
            {
                return 7;
            }
        };

        $data = $Entity->getReferenceData();

        $this->assertSame('uuid-1', $data['id']);
        $this->assertSame('INV-1', $data['id_str']);
        $this->assertSame('INV-1', $data['prefixedNumber']);
        $this->assertSame('uuid-1', $data['uuid']);
        $this->assertSame('gpi-1', $data['globalProcessId']);
        $this->assertSame(7, $data['currentStatusId']);
    }

    public function testGetReferenceDataWithoutCurrentStatusMethod(): void
    {
        $Entity = new class {
            use ErpEntityData;

            public function getUUID(): string
            {
                return 'uuid-2';
            }

            public function getPrefixedNumber(): string
            {
                return 'INV-2';
            }

            public function getGlobalProcessId(): string
            {
                return 'gpi-2';
            }
        };

        $data = $Entity->getReferenceData();

        $this->assertSame('uuid-2', $data['id']);
        $this->assertNull($data['currentStatusId']);
    }
}
