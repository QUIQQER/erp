<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\CalculationValue;
use QUI\ERP\Currency\Currency;

class CalculationValueTest extends TestCase
{
    public function testValueGetAndFormattedWithExplicitCurrencyAndPrecision(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Currency = $this->createMock(Currency::class);

        $Currency->expects($this->once())
            ->method('format')
            ->with(10.555, $Locale)
            ->willReturn('10.56 EUR');

        $Value = new CalculationValue(10.555, $Currency, 2);

        $this->assertSame(10.555, $Value->value());
        $this->assertSame(10.56, $Value->get());
        $this->assertSame('10.56 EUR', $Value->formatted($Locale));
    }

    public function testPrecisionFalseReturnsSameInstance(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Value = new CalculationValue(5.6789, $Currency, 3);

        $this->assertSame($Value, $Value->precision(false));
    }

    public function testPrecisionCreatesNewInstance(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Value = new CalculationValue(5.6789, $Currency, 4);
        $Rounded = $Value->precision(2);

        $this->assertNotSame($Value, $Rounded);
        $this->assertSame(5.6789, $Rounded->value());
        $this->assertSame(5.68, $Rounded->get());
    }

    public function testNonNumericConstructorInputKeepsDefaultNumber(): void
    {
        $Value = new CalculationValue('not-a-number', null, false);

        $this->assertSame(0, $Value->value());
        $this->assertSame(0.0, $Value->get());
    }
}
