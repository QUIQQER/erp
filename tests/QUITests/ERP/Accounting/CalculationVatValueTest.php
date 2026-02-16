<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\CalculationVatValue;
use QUI\ERP\Currency\Currency;

class CalculationVatValueTest extends TestCase
{
    public function testVatValueStoresTextVatAndNumber(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Value = new CalculationVatValue(123.456, 'MwSt 19%', 19, $Currency, 2);

        $this->assertSame('MwSt 19%', $Value->getTitle());
        $this->assertSame(19.0, $Value->getVat());
        $this->assertSame(123.46, $Value->get());
        $this->assertSame(123.456, $Value->value());
    }
}
