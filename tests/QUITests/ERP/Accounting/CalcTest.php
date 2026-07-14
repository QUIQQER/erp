<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Calc;
use QUI\Interfaces\Users\User as UserInterface;
use ReflectionClass;

class CalcTest extends TestCase
{
    public function testRoundParsesLocalizedNumericStrings(): void
    {
        $testCases = [
            [[','], '.', '1.234,56', 1234.56],
            [['.'], ',', '1,234.56', 1234.56],
            [[','], '.', '-1.234,56', -1234.56],
            [['.'], ',', '-1,234.56', -1234.56],
            [[','], '.', '1.000.000,00', 1000000.0],
            [['.'], ',', '1,000,000.00', 1000000.0],
            [[','], '.', '-1.000.000,00', -1000000.0],
            [['.'], ',', '-1,000,000.00', -1000000.0]
        ];

        foreach ($testCases as [$decimalSeparator, $groupingSeparator, $value, $expected]) {
            $Locale = $this->createMock(QUI\Locale::class);
            $Locale->method('getDecimalSeparator')->willReturn($decimalSeparator);
            $Locale->method('getGroupingSeparator')->willReturn($groupingSeparator);

            $this->assertSame($expected, $this->createCalc($Locale)->round($value));
        }
    }

    public function testRoundKeepsLegacyZeroForInvalidInput(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('getDecimalSeparator')->willReturn('.');
        $Locale->method('getGroupingSeparator')->willReturn(',');

        $this->assertSame(0.0, $this->createCalc($Locale)->round('invalid'));
    }

    private function createCalc(QUI\Locale $Locale): Calc
    {
        $Reflection = new ReflectionClass(Calc::class);
        $Calc = $Reflection->newInstanceWithoutConstructor();
        $User = $this->createMock(UserInterface::class);
        $User->method('getLocale')->willReturn($Locale);

        $Reflection->getProperty('User')->setValue($Calc, $User);

        return $Calc;
    }
}
