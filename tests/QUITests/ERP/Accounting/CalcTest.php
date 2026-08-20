<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Accounting\PriceFactors\FactorList;
use QUI\ERP\Constants;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\Money\Price;
use QUI\ERP\Utils\User as UserUtils;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Utils\Singleton;
use ReflectionClass;
use ReflectionProperty;

require_once __DIR__ . '/Fixtures/PaymentCalculationEntityInterface.php';

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

    public function testCalculateTotalReturnsOutstandingVatAmount(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getPrecision')->willReturn(2);
        $Currency->method('format')->willReturnCallback(
            static fn(float|int $amount): string => number_format((float)$amount, 2, '.', '')
        );

        $result = Calc::calculateTotal([[
            'calculated_sum' => 119,
            'calculated_vatsum' => 19,
            'calculated_paid' => 59.5,
            'calculated_toPay' => 59.5,
            'calculated_nettosum' => 100,
            'paid_status' => Constants::PAYMENT_STATUS_PART
        ]], $Currency);
        $expectedVatToPay = $result['vat_total'] - $result['vat_paid'];

        self::assertSame(9.48, $expectedVatToPay);
        self::assertSame($expectedVatToPay, $result['vat_toPay']);
        self::assertSame('9.48', $result['display_vat_toPay']);
    }

    public function testCalculateTotalAggregatesOpenAndPaidInvoices(): void
    {
        $Currency = $this->currency();
        $result = Calc::calculateTotal([
            [
                'calculated_sum' => 119,
                'calculated_vatsum' => 19,
                'calculated_paid' => 59.5,
                'calculated_toPay' => 59.5,
                'calculated_nettosum' => 100,
                'paid_status' => Constants::PAYMENT_STATUS_PART
            ],
            [
                'calculated_sum' => 107,
                'calculated_vatsum' => 7,
                'calculated_paid' => 0,
                'calculated_toPay' => 0,
                'calculated_nettosum' => 100,
                'paid_status' => Constants::PAYMENT_STATUS_PAID
            ]
        ], $Currency);

        self::assertSame(226.0, $result['brutto_total']);
        self::assertSame(166.5, $result['brutto_paid']);
        self::assertSame(59.5, $result['brutto_toPay']);
        self::assertSame(200.0, $result['netto_total']);
        self::assertSame(26.0, $result['vat_total']);
        self::assertSame('226.00', $result['display_brutto_total']);
    }

    public function testCalculateTotalReturnsZeroContractForEmptyList(): void
    {
        $result = Calc::calculateTotal([], $this->currency());

        self::assertSame(0, $result['netto_total']);
        self::assertSame(0, $result['vat_total']);
        self::assertSame(0, $result['brutto_total']);
        self::assertSame('0.00', $result['display_brutto_total']);
    }

    public function testCalculateTotalVatSupportsArrayJsonAndInvalidJson(): void
    {
        $vat = [['sum' => 19.5], ['sum' => 7.25]];

        self::assertSame(26.75, Calc::calculateTotalVatOfInvoice($vat));
        self::assertSame(26.75, Calc::calculateTotalVatOfInvoice(json_encode($vat, JSON_THROW_ON_ERROR)));
        self::assertSame(0, Calc::calculateTotalVatOfInvoice('{invalid json'));
    }

    public function testPaidEntityUsesStoredPaymentDataWithoutTransactionLookup(): void
    {
        $attributes = [
            'paid_status' => Constants::PAYMENT_STATUS_PAID,
            'paid_data' => json_encode([
                ['amount' => '20.25'],
                ['amount' => 30],
                ['ignored' => true]
            ], JSON_THROW_ON_ERROR),
            'paid_date' => 123
        ];
        $Entity = $this->createMock(ErpEntityInterface::class);
        $Entity->method('getAttribute')->willReturnCallback(
            static function (string $key) use (&$attributes): mixed {
                return $attributes[$key] ?? null;
            }
        );
        $Entity->method('setAttribute')->willReturnCallback(
            static function (string $key, mixed $value) use (&$attributes): void {
                $attributes[$key] = $value;
            }
        );

        $result = Calc::calculatePayments($Entity);

        self::assertSame(50.25, $result['paid']);
        self::assertSame(0, $result['toPay']);
        self::assertSame(123, $result['paidDate']);
        self::assertSame(Constants::PAYMENT_STATUS_PAID, $result['paidStatus']);
    }

    public function testCalculatePaymentsRejectsNonErpObjects(): void
    {
        $this->expectException(\QUI\ERP\Exception::class);
        $this->expectExceptionMessage('Object is not allowed to calculate');

        Calc::calculatePayments(new \stdClass());
    }

    public function testCalculatePaymentsUsesStoredShopCurrencyExchangeRate(): void
    {
        $CalculateCurrency = $this->currency();
        $CalculateCurrency->expects(self::once())
            ->method('amount')
            ->willReturnCallback(static fn(float|int $amount): float => round((float)$amount, 2));

        $TransactionCurrency = $this->createMock(Currency::class);
        $TransactionCurrency->method('getCode')->willReturn('USD');
        $TransactionCurrency->expects(self::never())->method('convert');

        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('isComplete')->willReturn(true);
        $Transaction->method('getAmount')->willReturn(110.0);
        $Transaction->method('getCurrency')->willReturn($TransactionCurrency);
        $Transaction->method('getDate')->willReturn('1700000000');
        $Transaction->method('getTxId')->willReturn('historical-shop-rate');
        $Transaction->method('getData')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                Calc::TRANSACTION_ATTR_SHOP_CURRENCY_EXCHANGE_RATE => 1.1,
                default => null
            }
        );

        $TransactionHandler = $this->createMock(TransactionHandler::class);
        $TransactionHandler->expects(self::once())
            ->method('getTransactionsByHash')
            ->with('payment-calculation-hash')
            ->willReturn([$Transaction]);

        $ArticleList = $this->createMock(ArticleList::class);
        $ArticleList->method('getCalculations')->willReturn(['sum' => 100.0]);

        $attributes = [
            'paid_status' => Constants::PAYMENT_STATUS_OPEN,
            'paid_date' => 1700000000
        ];
        $Entity = $this->createMock(PaymentCalculationEntityInterface::class);
        $Entity->method('getHash')->willReturn('payment-calculation-hash');
        $Entity->method('getCurrency')->willReturn($CalculateCurrency);
        $Entity->method('getArticles')->willReturn($ArticleList);
        $Entity->method('getAttribute')->willReturnCallback(
            static function (string $key) use (&$attributes): mixed {
                return $attributes[$key] ?? null;
            }
        );
        $Entity->method('setAttribute')->willReturnCallback(
            static function (string $key, mixed $value) use (&$attributes): void {
                $attributes[$key] = $value;
            }
        );

        $SingletonInstances = new ReflectionProperty(Singleton::class, 'instances');
        $originalSingletonInstances = $SingletonInstances->getValue();
        $testSingletonInstances = $originalSingletonInstances;
        $testSingletonInstances[TransactionHandler::class] = $TransactionHandler;

        $DefaultCurrency = new ReflectionProperty(CurrencyHandler::class, 'Default');
        $originalDefaultCurrency = $DefaultCurrency->getValue();

        try {
            $SingletonInstances->setValue(null, $testSingletonInstances);
            $DefaultCurrency->setValue(null, $CalculateCurrency);

            $result = Calc::calculatePayments($Entity);
        } finally {
            $DefaultCurrency->setValue(null, $originalDefaultCurrency);
            $SingletonInstances->setValue(null, $originalSingletonInstances);
        }

        self::assertSame(100.0, $result['paid']);
        self::assertSame(0.0, $result['toPay']);
        self::assertSame(Constants::PAYMENT_STATUS_PAID, $result['paidStatus']);
        self::assertSame(100.0, $result['paidData'][0]['amount']);
        self::assertSame('historical-shop-rate', $result['paidData'][0]['txid']);
    }

    public function testCalculatePaymentsUsesTargetRateAndLiveFallbackForPartialPayment(): void
    {
        $CalculateCurrency = $this->currency();
        $CalculateCurrency->expects(self::exactly(2))
            ->method('amount')
            ->willReturnCallback(static fn(float|int $amount): float => round((float)$amount, 2));

        $TargetCurrency = $this->createMock(Currency::class);
        $TargetCurrency->method('getCode')->willReturn('USD');
        $TargetCurrency->expects(self::never())->method('convert');

        $LiveCurrency = $this->createMock(Currency::class);
        $LiveCurrency->method('getCode')->willReturn('GBP');
        $LiveCurrency->expects(self::once())
            ->method('convert')
            ->with(20.0, $CalculateCurrency)
            ->willReturn(25.0);

        $Incomplete = $this->createMock(Transaction::class);
        $Incomplete->method('isComplete')->willReturn(false);
        $Incomplete->expects(self::never())->method('getAmount');

        $TargetTransaction = $this->createMock(Transaction::class);
        $TargetTransaction->method('isComplete')->willReturn(true);
        $TargetTransaction->method('getAmount')->willReturn(50.0);
        $TargetTransaction->method('getCurrency')->willReturn($TargetCurrency);
        $TargetTransaction->method('getDate')->willReturn('1700000100');
        $TargetTransaction->method('getTxId')->willReturn('historical-target-rate');
        $TargetTransaction->method('getData')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                Calc::TRANSACTION_ATTR_TARGET_CURRENCY => 'EUR',
                Calc::TRANSACTION_ATTR_TARGET_CURRENCY_EXCHANGE_RATE => 0.5,
                default => null
            }
        );

        $LiveTransaction = $this->createMock(Transaction::class);
        $LiveTransaction->method('isComplete')->willReturn(true);
        $LiveTransaction->method('getAmount')->willReturn(20.0);
        $LiveTransaction->method('getCurrency')->willReturn($LiveCurrency);
        $LiveTransaction->method('getDate')->willReturn('1700000200');
        $LiveTransaction->method('getTxId')->willReturn('live-fallback-rate');
        $LiveTransaction->method('getData')->willReturn(null);

        $TransactionHandler = $this->createMock(TransactionHandler::class);
        $TransactionHandler->method('getTransactionsByHash')->willReturn([
            $Incomplete,
            $TargetTransaction,
            $LiveTransaction
        ]);

        $ArticleList = $this->createMock(ArticleList::class);
        $ArticleList->method('getCalculations')->willReturn(['sum' => 200.0]);

        $attributes = [
            'paid_status' => Constants::PAYMENT_STATUS_OPEN,
            'paid_date' => 1700000200
        ];
        $Entity = $this->createMock(PaymentCalculationEntityInterface::class);
        $Entity->method('getId')->willReturn(701);
        $Entity->method('getHash')->willReturn('conversion-payment-hash');
        $Entity->method('getCurrency')->willReturn($CalculateCurrency);
        $Entity->method('getArticles')->willReturn($ArticleList);
        $Entity->method('getAttribute')->willReturnCallback(
            static function (string $key) use (&$attributes): mixed {
                return $attributes[$key] ?? null;
            }
        );
        $Entity->method('setAttribute')->willReturnCallback(
            static function (string $key, mixed $value) use (&$attributes): void {
                $attributes[$key] = $value;
            }
        );

        $SingletonInstances = new ReflectionProperty(Singleton::class, 'instances');
        $originalSingletonInstances = $SingletonInstances->getValue();
        $instances = $originalSingletonInstances;
        $instances[TransactionHandler::class] = $TransactionHandler;

        $DefaultCurrency = new ReflectionProperty(CurrencyHandler::class, 'Default');
        $originalDefaultCurrency = $DefaultCurrency->getValue();

        try {
            $SingletonInstances->setValue(null, $instances);
            $DefaultCurrency->setValue(null, $CalculateCurrency);
            $result = Calc::calculatePayments($Entity);
        } finally {
            $DefaultCurrency->setValue(null, $originalDefaultCurrency);
            $SingletonInstances->setValue(null, $originalSingletonInstances);
        }

        self::assertSame(125.0, $result['paid']);
        self::assertSame(75.0, $result['toPay']);
        self::assertSame(Constants::PAYMENT_STATUS_PART, $result['paidStatus']);
        self::assertSame([100.0, 25.0], array_column($result['paidData'], 'amount'));
        self::assertSame(1700000200, $result['paidDate']);
    }

    public function testCalcAccessorsAndCallbacklessFacadesDelegateToDomainObjects(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $replacementLocale = $this->createMock(QUI\Locale::class);
        $User = $this->createMock(UserInterface::class);
        $replacementUser = $this->createMock(UserInterface::class);
        $Calc = $this->createCalc($Locale);

        $Calc->setUser($replacementUser);
        self::assertSame($replacementUser, $Calc->getUser());
        self::assertSame($Locale, $Calc->getLocale());
        $Calc->setLocale($replacementLocale);
        self::assertSame($replacementLocale, $Calc->getLocale());

        $originalLocale = QUI::$Locale;
        try {
            QUI::$Locale = $Locale;
            $Calc->resetLocale();
            self::assertSame($Locale, $Calc->getLocale());
        } finally {
            QUI::$Locale = $originalLocale;
        }

        $Currency = $this->currency();
        (new ReflectionProperty(Calc::class, 'Currency'))->setValue($Calc, $Currency);
        self::assertSame($Currency, $Calc->getCurrency());

        $List = $this->createMock(ArticleList::class);
        $List->expects(self::once())->method('calc')->willReturnSelf();
        self::assertSame($List, $Calc->calcArticleList($List));

        $Price = $this->createMock(Price::class);
        $Article = $this->createMock(ArticleInterface::class);
        $Article->expects(self::once())->method('calc')->with($Calc);
        $Article->method('getPrice')->willReturn($Price);
        self::assertSame($Price, $Calc->calcArticlePrice($Article));
    }

    public function testAllowedCalculationContract(): void
    {
        self::assertTrue(Calc::isAllowedForCalculation($this->createMock(ErpEntityInterface::class)));
        self::assertFalse(Calc::isAllowedForCalculation(new \stdClass()));
    }

    public function testVatTextReflectsNettoBruttoAndZeroVat(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key): string => $package . ':' . $key
        );
        $nettoUser = $this->calculationUser(UserUtils::IS_NETTO_USER);
        $bruttoUser = $this->calculationUser(UserUtils::IS_BRUTTO_USER);

        self::assertSame('quiqqer/tax:message.vat.text.netto', Calc::getVatText(19, $nettoUser, $Locale));
        self::assertSame('quiqqer/tax:message.vat.text.brutto', Calc::getVatText(19, $bruttoUser, $Locale));
        self::assertSame('', Calc::getVatText(0, $nettoUser, $Locale));
        self::assertSame('', Calc::getVatText(0, $bruttoUser, $Locale));
    }

    public function testArticlePriceCalculationCoversNettoBruttoAndDiscounts(): void
    {
        $Currency = $this->currency();

        foreach (
            [
            [UserUtils::IS_NETTO_USER, null, 10.0, 20.0],
            [UserUtils::IS_BRUTTO_USER, null, 11.9, 23.8],
            [UserUtils::IS_NETTO_USER, [10, Calc::CALCULATION_PERCENTAGE], 9.0, 18.0],
            [UserUtils::IS_BRUTTO_USER, [2, Calc::CALCULATION_COMPLEMENT], 10.71, 21.42]
            ] as [$status, $discount, $expectedPrice, $expectedSum]
        ) {
            $Article = new \QUI\ERP\Accounting\Article([
                'id' => 10,
                'articleNo' => 'CALC-10',
                'title' => 'Calculated article',
                'unitPrice' => 10,
                'quantity' => 2,
                'vat' => 19
            ]);
            $Article->setCurrency($Currency);
            $Article->setUser($this->calculationUser($status));

            if ($discount !== null) {
                $Article->setDiscount($discount[0], $discount[1]);
            }

            $Article->calc($this->calcFor($Article->getUser()));
            $data = $Article->toArray();

            self::assertEqualsWithDelta($expectedPrice, $data['calculated']['price'], 0.001);
            self::assertEqualsWithDelta($expectedSum, $data['calculated']['sum'], 0.001);
            self::assertSame(19.0, $data['vat']);
        }
    }

    public function testArticleListCalculationAggregatesArticlesVatAndPriceFactors(): void
    {
        $Currency = $this->currency();
        $User = $this->calculationUser(UserUtils::IS_NETTO_USER);
        $List = new ArticleList();
        $List->setCurrency($Currency);
        $List->setUser($User);
        $List->addArticle(new Article([
            'id' => 11,
            'articleNo' => 'LIST-11',
            'title' => 'Consulting',
            'unitPrice' => 100,
            'quantity' => 2,
            'vat' => 19
        ]));
        $List->addArticle(new Article([
            'id' => 12,
            'articleNo' => 'LIST-12',
            'title' => 'Book',
            'unitPrice' => 20,
            'quantity' => 1,
            'vat' => 7
        ]));
        $List->importPriceFactors(new FactorList([
            $this->factor([
                'title' => 'Shipping',
                'value' => 10,
                'nettoSum' => 10,
                'sum' => 11.9,
                'vat' => 19
            ]),
            $this->factor([
                'title' => 'Service fee',
                'value' => 10,
                'calculation' => Calc::CALCULATION_PERCENTAGE,
                'calculation_basis' => Calc::CALCULATION_BASIS_NETTO,
                'vat' => 19
            ]),
            $this->factor([
                'title' => 'Voucher',
                'value' => -5,
                'calculation_basis' => Calc::CALCULATION_GRAND_TOTAL
            ])
        ]));

        $List->calc($this->calcFor($User));
        $calculations = $List->getCalculations();

        self::assertSame(220.0, $calculations['subSum']);
        self::assertSame(252.0, $calculations['nettoSum']);
        self::assertSame(297.48, $calculations['grandSubSum']);
        self::assertSame(292.48, $calculations['sum']);
        self::assertSame([19, 7], array_map('intval', array_keys($calculations['vatArray'])));
        self::assertFalse($calculations['isEuVat']);
        self::assertTrue($calculations['isNetto']);
    }

    public function testBruttoArticleListCalculatesVatInclusivePriceFactors(): void
    {
        $Currency = $this->currency();
        $User = $this->calculationUser(UserUtils::IS_BRUTTO_USER);
        $List = new ArticleList();
        $List->setCurrency($Currency);
        $List->setUser($User);
        $List->addArticle(new Article([
            'id' => 13,
            'articleNo' => 'LIST-13',
            'title' => 'Gross price article',
            'unitPrice' => 100,
            'quantity' => 1,
            'vat' => 19
        ]));
        $List->importPriceFactors(new FactorList([
            $this->factor([
                'title' => 'Gross shipping',
                'value' => 10,
                'nettoSum' => 10,
                'sum' => 11.9,
                'calculation_basis' => Calc::CALCULATION_BASIS_VAT_BRUTTO,
                'vat' => 19
            ]),
            $this->factor([
                'title' => 'Gross service fee',
                'value' => 10,
                'calculation' => Calc::CALCULATION_PERCENTAGE,
                'calculation_basis' => Calc::CALCULATION_BASIS_VAT_BRUTTO,
                'vat' => 19
            ])
        ]));

        $List->calc($this->calcFor($User));
        $calculations = $List->getCalculations();

        self::assertFalse($calculations['isNetto']);
        self::assertSame(119.0, $calculations['subSum']);
        self::assertGreaterThan(119, $calculations['sum']);
        self::assertArrayHasKey(19, $calculations['vatArray']);
        self::assertSame(
            $calculations['nettoSum'] + $calculations['vatArray'][19]['sum'],
            $calculations['sum']
        );
    }

    public function testGrandTotalFactorCannotMakeAnEmptyListNegative(): void
    {
        $Currency = $this->currency();
        $User = $this->calculationUser(UserUtils::IS_NETTO_USER);
        $List = new ArticleList();
        $List->setCurrency($Currency);
        $List->setUser($User);
        $List->importPriceFactors(new FactorList([
            $this->factor([
                'title' => 'Voucher',
                'value' => -10,
                'calculation_basis' => Calc::CALCULATION_GRAND_TOTAL
            ])
        ]));

        $List->recalculate($this->calcFor($User));

        self::assertSame(0, $List->getCalculations()['sum']);
        self::assertSame(0, $List->getCalculations()['nettoSum']);
    }

    private function createCalc(QUI\Locale $Locale): Calc
    {
        $Reflection = new ReflectionClass(Calc::class);
        $Calc = $Reflection->newInstanceWithoutConstructor();
        $User = $this->createMock(UserInterface::class);
        $User->method('getLocale')->willReturn($Locale);

        $Reflection->getProperty('User')->setValue($Calc, $User);
        $Reflection->getProperty('Locale')->setValue($Calc, $Locale);

        return $Calc;
    }

    private function currency(): Currency
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getPrecision')->willReturn(2);
        $Currency->method('getCode')->willReturn('EUR');
        $Currency->method('getSign')->willReturn('€');
        $Currency->method('getExchangeRate')->willReturn(1.0);
        $Currency->method('toArray')->willReturn(['code' => 'EUR', 'sign' => '€', 'rate' => 1.0]);
        $Currency->method('format')->willReturnCallback(
            static fn(float|int $amount): string => number_format((float)$amount, 2, '.', '')
        );

        return $Currency;
    }

    private function calculationUser(int $status): UserInterface
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('getDecimalSeparator')->willReturn(['.']);
        $Locale->method('getGroupingSeparator')->willReturn(',');
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key): string => $package . ':' . $key
        );
        $User = $this->createMock(UserInterface::class);
        $User->method('getLocale')->willReturn($Locale);
        $User->method('getUUID')->willReturn('calc-user-' . $status);
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => $key === 'RUNTIME_NETTO_BRUTTO_STATUS' ? $status : null
        );

        return $User;
    }

    private function calcFor(UserInterface $User): Calc
    {
        $Reflection = new ReflectionClass(Calc::class);
        $Calc = $Reflection->newInstanceWithoutConstructor();
        $Reflection->getProperty('User')->setValue($Calc, $User);
        $Reflection->getProperty('Locale')->setValue($Calc, $User->getLocale());

        return $Calc;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function factor(array $data): array
    {
        return array_merge([
            'title' => 'Factor',
            'description' => '',
            'sum' => 0,
            'sumFormatted' => '',
            'nettoSum' => 0,
            'nettoSumFormatted' => '',
            'visible' => 1,
            'value' => 0,
            'calculation' => Calc::CALCULATION_COMPLEMENT,
            'calculation_basis' => Calc::CALCULATION_BASIS_NETTO,
            'vat' => 0
        ], $data);
    }
}
