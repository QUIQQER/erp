<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Process;

class ProcessTest extends TestCase
{
    public function testGetUuidReturnsConstructorProcessId(): void
    {
        $Process = new Process('erp-process-123');

        $this->assertSame('erp-process-123', $Process->getUUID());
    }

    public function testActiveDateConstant(): void
    {
        $this->assertSame('2024-08-01 00:00:00', Process::PROCESS_ACTIVE_DATE);
    }
}
