<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Processes;
use QUI\Exception;
use ReflectionClass;

class ProcessesTest extends TestCase
{
    public function testGetWantedPluginList(): void
    {
        $Processes = new Processes();
        $list = $Processes->getWantedPluginList();

        $this->assertContains('quiqqer/invoice', $list);
        $this->assertContains('quiqqer/order', $list);
        $this->assertContains('quiqqer/payment-transactions', $list);
        $this->assertCount(10, $list);
    }

    public function testGetEarlierDateHelper(): void
    {
        $Processes = new Processes();
        $Reflection = new ReflectionClass(Processes::class);
        $method = $Reflection->getMethod('getEarlierDate');

        $this->assertSame('2026-01-01', $method->invoke($Processes, null, '2026-01-01'));
        $this->assertSame('2026-01-01', $method->invoke($Processes, '2026-01-01', null));
        $this->assertSame('2025-12-31', $method->invoke($Processes, '2025-12-31', '2026-01-01'));
    }

    public function testGetEntityThrowsExceptionForUnknownPlugin(): void
    {
        $Processes = new Processes();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $Processes->getEntity('hash-does-not-exist', 'custom/unknown-plugin');
    }

    public function testGetEntityWithAutoPluginResolutionThrowsNotFound(): void
    {
        $Processes = new Processes();

        try {
            $Processes->getEntity('definitely-unknown-hash-' . uniqid(), false);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $Exception) {
            $this->assertSame(404, $Exception->getCode());
        }
    }

    public function testGetListReturnsArray(): void
    {
        $Processes = new Processes();
        $result = $Processes->getList();

        $this->assertIsArray($result);
    }
}
