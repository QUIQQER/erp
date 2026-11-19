<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Enums\Accounting\CalculationMode;

class ArticleViewManualPriceTest extends TestCase
{
    #[DataProvider('prices')]
    public function testBothModesPrintTheSameStoredCalculatedFieldsWithoutChangingAmounts(
        CalculationMode $mode,
        bool $isNetto,
        float $unitPrice,
        float $cachedPrice,
        bool $discount,
        string $expected
    ): void {
        $data = [
            'unitPrice' => $unitPrice,
            'quantity' => 1,
            'vat' => 19,
            'calculated' => [
                'price' => $cachedPrice,
                'basisPrice' => $discount ? $unitPrice : $cachedPrice,
                'nettoPrice' => $cachedPrice,
                'nettoBasisPrice' => $cachedPrice,
                'nettoSum' => 8.395,
                'nettoSubSum' => 8.395,
                'sum' => 9.99,
                'vatArray' => ['vat' => 19, 'sum' => 1.5951, 'text' => '19%'],
                'isNetto' => $isNetto,
                'isEuVat' => false,
                'bruttoCalculated' => ['unitPrice' => 9.99, 'sum' => 9.99]
            ]
        ];

        if ($discount) {
            $data['discount'] = '10%';
        }

        $Article = new Article($data);
        $Article->setCalculationMode($mode);
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Currency->method('getPrecision')->willReturn(2);
        $Currency->method('format')->willReturnCallback(
            static fn (float | int $value): string => number_format($value, 2, '.', '') . ' EUR'
        );
        $Article->setCurrency($Currency);
        $Article->getDiscount()?->setCurrency($Currency);
        $before = $Article->toArray();
        $View = $Article->getView();
        $View->setCurrency($Currency);
        $html = $View->toHTML();
        self::assertSame(1, preg_match('~class="articles-article-unitprice-price">([^<]+)</span>~', $html, $matches));
        self::assertSame($expected, trim($matches[1]));
        self::assertSame($before, $Article->toArray());
    }

    public static function prices(): iterable
    {
        yield 'manual net uses entered calculated price' => [CalculationMode::MANUAL, true, 8.39, 8.39, false, '8.39 EUR'];
        yield 'manual net retains entered precision' => [CalculationMode::MANUAL, true, 8.395, 8.395, false, '8.40 EUR'];
        yield 'manual gross uses entered gross unit price' => [CalculationMode::MANUAL, false, 8.39, 9.99, false, '9.99 EUR'];
        yield 'manual zero is intentional' => [CalculationMode::MANUAL, true, 8.39, 0.0, false, '0.00 EUR'];
        yield 'manual discount displays entered base price' => [CalculationMode::MANUAL, true, 10.0, 8.39, true, '10.00 EUR'];
        yield 'automatic uses existing calculated price' => [CalculationMode::AUTOMATIC, true, 8.39, 4.2, false, '4.20 EUR'];
    }
}
