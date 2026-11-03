<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Tax\TaxEntryEmpty;
use QUI\ERP\Tax\Utils as TaxUtils;
use QUI\ERP\Utils\User as UserUtils;
use ReflectionClass;
use ReflectionProperty;

class ArticleVatTest extends TestCase
{
    public function testConfirmedRatesSurviveRecalculationForIrishSupplier(): void
    {
        $Supplier = $this->createMock(QUI\Interfaces\Users\User::class);
        $Supplier->method('getId')->willReturn(644);
        $Supplier->method('getUUID')->willReturn('phpunit-644-irish-supplier');
        $Country = $this->createMock(QUI\Countries\Country::class);
        $Country->method('getCode')->willReturn('IE');
        $Supplier->method('getCountry')->willReturn($Country);
        $Locale = QUI::getLocale();
        $Supplier->method('getLocale')->willReturn($Locale);
        $Supplier->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => $key === 'RUNTIME_NETTO_BRUTTO_STATUS'
                ? UserUtils::IS_BRUTTO_USER
                : null
        );
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getPrecision')->willReturn(2);
        $Currency->method('getCode')->willReturn('EUR');

        // Supply the country's tax result in memory, without changing supplier/tax master data.
        $Tax = $this->createMock(TaxEntryEmpty::class);
        $Tax->method('getValue')->willReturn(23.0);
        $Taxes = new ReflectionProperty(TaxUtils::class, 'userTaxes');
        $previousTaxes = $Taxes->getValue();
        $Taxes->setValue(null, [644 => $Tax]);

        try {
            foreach (['', null] as $missingVat) {
                $Article = new Article(['vat' => $missingVat]);
                $Article->setUser($Supplier);
                self::assertSame(23.0, $Article->getVat());
            }

            foreach ([19, 0, '0', 7, 5.5] as $vat) {
                $Article = new Article([
                    'title' => 'Confirmed receipt position',
                    'unitPrice' => 100,
                    'quantity' => 1,
                    'vat' => $vat
                ]);
                $Article->setUser($Supplier);
                $Article->setCurrency($Currency);
                $Reflection = new ReflectionClass(Calc::class);
                $Calc = $Reflection->newInstanceWithoutConstructor();
                $Reflection->getProperty('User')->setValue($Calc, $Supplier);
                $Reflection->getProperty('Locale')->setValue($Calc, $Locale);
                $Reflection->getProperty('Currency')->setValue($Calc, $Currency);
                $Article->calc($Calc);

                self::assertSame((float)$vat, $Article->getVat());
                self::assertEqualsWithDelta(100 + (float)$vat, $Article->getSum()->value(), 0.001);
                self::assertSame((float)$vat, $Article->toArray()['vat']);
            }
        } finally {
            $Taxes->setValue(null, $previousTaxes);
        }
    }
}
