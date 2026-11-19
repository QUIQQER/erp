<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleDiscount;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Defaults;
use QUI\ERP\Enums\Accounting\CalculationMode;

class ArticleListNegationTest extends TestCase
{
    public function testManualReversalNegatesIndependentAmountsWithoutRecalculating(): void
    {
        $snapshot = $this->snapshot();
        $List = new ArticleList($snapshot);
        $List->negate();
        $Calc = $this->createMock(Calc::class);
        $Calc->expects(self::never())->method('calcArticleList');
        $Calc->expects(self::never())->method('calcArticlePrice');
        $List->calc($Calc);
        $data = $List->toArray();

        self::assertSame(CalculationMode::MANUAL, $List->getCalculationMode());
        self::assertSame(-9.99, $data['calculations']['sum']);
        self::assertSame(-9.9901, $data['calculations']['grandSubSum']);
        self::assertSame(-8.395, $data['calculations']['nettoSum']);
        self::assertSame(-1.5951, $data['calculations']['vatArray'][19]['sum']);
        self::assertSame(-0.333333, $data['calculations']['vatArray'][7]['sum']);
        self::assertSame(-1.234567, $data['calculations']['vatSum']);
        self::assertSame(-7.123456, $data['calculations']['duePayableAmount']);
        self::assertSame(2, $data['articles'][0]['quantity']);
        self::assertSame(19.0, $data['articles'][0]['vat']);
        self::assertSame(-8.395, $data['articles'][0]['unitPrice']);
        self::assertSame(-8.395, $data['articles'][0]['calculated']['nettoSum']);
        self::assertSame(-1.5951, $data['articles'][0]['calculated']['vatArray']['sum']);
        $gross = $data['articles'][0]['calculated']['bruttoCalculated'];
        self::assertSame(-9.99, $gross['sum']);
        self::assertSame(-9.9901, $gross['quantity_sum']);
        self::assertSame(-4.99505, $gross['unitPrice']);
        self::assertSame(-9.99, $gross['calculated']['sum']);
        self::assertSame(19, $gross['vat']);
        self::assertSame(123.456, $data['articles'][0]['customData']['sum']);
        self::assertSame(10.0, $List->getArticles()[0]->getDiscount()->getValue());
        self::assertSame(-0.1234, $data['priceFactors'][0]['sum']);
        self::assertSame(-0.1, $data['priceFactors'][0]['nettoSum']);
        self::assertSame(5, $data['priceFactors'][0]['value']);
        self::assertSame(-0.1234, $data['priceFactors'][1]['value']);
        self::assertSame($snapshot['calculations']['vatText'], $data['calculations']['vatText']);

        $Reloaded = new ArticleList(json_decode($List->serialize(), true, 512, JSON_THROW_ON_ERROR));
        self::assertEquals($data, $Reloaded->toUniqueList()->toArray());
    }

    public function testReversingTwiceRestoresTheOriginalNumericAmountsAndFlatDiscounts(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['articles'][0]['discount'] = (new ArticleDiscount(0.456789, Calc::CALCULATION_COMPLEMENT))->toJSON();
        $snapshot['articles'][0]['calculated']['bruttoCalculated']['discount'] = 0.123456;
        $snapshot['articles'][0]['calculated']['bruttoCalculated']['display_discount'] =
            Defaults::getCurrency()->format(0.123456);
        $List = new ArticleList($snapshot);
        $before = $List->toArray();
        $List->negate();
        self::assertSame(-0.456789, $List->getArticles()[0]->getDiscount()->getValue());
        $gross = $List->toArray()['articles'][0]['calculated']['bruttoCalculated'];
        self::assertSame(-0.123456, $gross['discount']);
        self::assertSame(Defaults::getCurrency()->format(-0.123456), $gross['display_discount']);
        $List->negate();
        $after = $List->toArray();

        self::assertSame($before['calculations'], $after['calculations']);
        self::assertSame($before['articles'], $after['articles']);
        self::assertSame(0.1234, $after['priceFactors'][0]['sum']);
        self::assertSame(5, $after['priceFactors'][0]['value']);
        self::assertSame(0.1234, $after['priceFactors'][1]['value']);
    }

    public function testTheExistingAutomaticReversalPathIsNotChanged(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = 'automatic';
        $List = new ArticleList($snapshot);
        $before = $List->getCalculations();
        $List->negate();

        self::assertSame(CalculationMode::AUTOMATIC, $List->getCalculationMode());
        self::assertSame($before, $List->getCalculations());
        self::assertSame(8.395, $List->getArticles()[0]->getUnitPrice()->value());
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $factor = [
            'title' => 'Original surcharge',
            'description' => '',
            'sum' => 0.1234,
            'sumFormatted' => '',
            'nettoSum' => 0.1,
            'nettoSumFormatted' => '',
            'visible' => 1,
            'vat' => 19,
            'value' => 5,
            'calculation' => Calc::CALCULATION_PERCENTAGE
        ];
        $flatFactor = $factor;
        $flatFactor['calculation'] = Calc::CALCULATION_COMPLEMENT;
        $flatFactor['value'] = 0.1234;

        return [
            'calculationMode' => 'manual',
            'articles' => [[
                'title' => 'Original manual position',
                'unitPrice' => 8.395,
                'quantity' => 2,
                'vat' => 19,
                'discount' => '10%',
                'customData' => ['sum' => 123.456],
                'calculated' => [
                    'price' => 8.395,
                    'basisPrice' => 8.395,
                    'sum' => 8.395,
                    'nettoPriceNotRounded' => 8.395,
                    'nettoPrice' => 8.395,
                    'nettoBasisPrice' => 8.395,
                    'nettoSubSum' => 8.395,
                    'nettoSum' => 8.395,
                    'vatArray' => ['vat' => 19, 'sum' => 1.5951, 'text' => 'VAT 19%'],
                    'isNetto' => true,
                    'isEuVat' => false,
                    'bruttoCalculated' => [
                        'vat' => 19,
                        'sum' => 9.99,
                        'quantity_sum' => 9.9901,
                        'unitPrice' => 4.99505,
                        'calculated' => ['sum' => 9.99, 'basisPrice' => 4.99505]
                    ]
                ]
            ]],
            'calculations' => [
                'sum' => 9.99,
                'subSum' => 8.395,
                'grandSubSum' => 9.9901,
                'nettoSubSum' => 8.395,
                'nettoSum' => 8.395,
                'vatSum' => 1.234567,
                'duePayableAmount' => 7.123456,
                'vatArray' => [
                    19 => ['vat' => 19, 'sum' => 1.5951, 'text' => 'VAT 19%'],
                    7 => ['vat' => 7, 'sum' => 0.333333, 'text' => 'VAT 7%']
                ],
                'vatText' => [19 => 'VAT 19%', 7 => 'VAT 7%'],
                'isNetto' => true,
                'isEuVat' => false,
                'currencyData' => Defaults::getCurrency()->toArray()
            ],
            'priceFactors' => [$factor, $flatFactor]
        ];
    }
}
