<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Defaults;
use ReflectionClass;

class DefaultsTest extends TestCase
{
    public function testGetPrecisionReturnsEightInPhpUnitRuntime(): void
    {
        $this->assertSame(8, Defaults::getPrecision());
    }

    public function testGetTimestampFormatUsesCachedValue(): void
    {
        $Reflection = new ReflectionClass(Defaults::class);
        $property = $Reflection->getProperty('timestampFormat');
        $property->setValue(['de' => 'dd.MM.yyyy, HH:mm:ss']);

        $this->assertSame('dd.MM.yyyy, HH:mm:ss', Defaults::getTimestampFormat('de'));
    }

    public function testGetDateFormatUsesCachedValue(): void
    {
        $Reflection = new ReflectionClass(Defaults::class);
        $property = $Reflection->getProperty('dateFormat');
        $property->setValue(['de' => 'dd.MM.yyyy']);

        $this->assertSame('dd.MM.yyyy', Defaults::getDateFormat('de'));
    }

    public function testPhpUnitRuntimeDetectionIsTrue(): void
    {
        $Reflection = new ReflectionClass(Defaults::class);
        $method = $Reflection->getMethod('isPhpUnitRuntime');
        $this->assertTrue($method->invoke(null));
    }
}
