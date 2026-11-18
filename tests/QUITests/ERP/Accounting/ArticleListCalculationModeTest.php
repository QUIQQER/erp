<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Defaults;
use QUI\ERP\Enums\Accounting\CalculationMode;
use QUI\ERP\Exception;

class ArticleListCalculationModeTest extends TestCase
{
    public function testLegacyListsDefaultToAutomaticWithoutChangingStoredAmounts(): void
    {
        $snapshot = $this->snapshot();

        foreach ([new ArticleList($snapshot), new ArticleListUnique($snapshot), new ArticleList()] as $List) {
            self::assertSame(CalculationMode::AUTOMATIC, $List->getCalculationMode());
            self::assertSame('automatic', $List->toArray()['calculationMode']);
        }

        self::assertSame($snapshot['calculations'], (new ArticleList($snapshot))->getCalculations());
    }

    #[DataProvider('calculationModes')]
    public function testModeAndOriginalAmountsSurviveJsonReloadingAndUniqueConversion(CalculationMode $mode): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = $mode->value;
        $List = new ArticleList($snapshot);
        $serialized = $List->serialize();
        $Reloaded = new ArticleList(json_decode($serialized, true, 512, JSON_THROW_ON_ERROR));
        $Unique = $List->toUniqueList();

        foreach ([$List, $Reloaded, $Unique, ArticleListUnique::unserialize($Unique->serialize())] as $Copy) {
            self::assertSame($mode, $Copy->getCalculationMode());
            $data = $Copy->toArray();
            self::assertSame($mode->value, $data['calculationMode']);
            self::assertSame(8.395, $data['articles'][0]['unitPrice']);
            foreach ($snapshot['articles'][0]['calculated'] as $key => $value) {
                self::assertSame($value, $data['articles'][0]['calculated'][$key], $key);
            }

            foreach ($snapshot['calculations'] as $key => $value) {
                if ($key === 'currencyData') {
                    // JSON does not preserve the distinction between an integral float rate and an integer rate.
                    self::assertEquals($value, $data['calculations'][$key], $key);
                    continue;
                }

                self::assertSame($value, $data['calculations'][$key], $key);
            }

            $Calculations = new Calculations($Copy->getCalculations(), $Copy->getArticles());
            self::assertSame(8.395, $Calculations->getNettoSum()->value());
            self::assertSame(1.5951, $Calculations->getVatSum()->value());
            self::assertSame(9.99, $Calculations->getSum()->value());
        }
    }

    public function testModeCanBeChangedWithoutRecalculatingOrReplacingTheSnapshot(): void
    {
        $List = new ArticleList($this->snapshot());
        $before = $List->toArray();

        foreach ([CalculationMode::MANUAL, CalculationMode::AUTOMATIC] as $mode) {
            $List->setCalculationMode($mode);
            $expected = $before;
            $expected['calculationMode'] = $mode->value;

            self::assertSame($expected, $List->toArray());
        }
    }

    public function testUniqueSnapshotRetainsItsModeWhenConvertedBackToAMutableList(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = 'manual';
        $Unique = new ArticleListUnique($snapshot);
        $List = new ArticleList($Unique->toArray());

        self::assertSame(CalculationMode::MANUAL, $List->getCalculationMode());
        self::assertSame($snapshot['calculations'], $List->getCalculations());
    }

    public function testCalculationCacheInvalidationDoesNotChangeTheDocumentMode(): void
    {
        $List = new ArticleList($this->snapshot());
        $List->setCalculationMode(CalculationMode::MANUAL);
        // Adding an article invalidates the calculation cache; the document policy is independent of it.
        $List->addArticle($List->getArticles()[0]);

        self::assertSame(CalculationMode::MANUAL, $List->getCalculationMode());
    }

    #[DataProvider('invalidModes')]
    public function testInvalidStoredModeIsRejectedInsteadOfSilentlyEnablingAutomaticCalculation(mixed $mode): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = $mode;

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid article list calculation mode');

        new ArticleListUnique($snapshot);
    }

    public static function calculationModes(): iterable
    {
        yield 'automatic' => [CalculationMode::AUTOMATIC];
        yield 'manual' => [CalculationMode::MANUAL];
    }

    public static function invalidModes(): iterable
    {
        yield 'unknown mode' => ['disabled'];
        yield 'different case' => ['MANUAL'];
        yield 'empty mode' => [''];
        yield 'null mode' => [null];
        yield 'boolean mode' => [false];
        yield 'numeric mode' => [0];
        yield 'array mode' => [[]];
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'articles' => [[
                'id' => 551,
                'articleNo' => 'DRILLISCH-551',
                'title' => 'Paketpreis',
                'unitPrice' => 8.395,
                'quantity' => 1,
                'vat' => 19,
                'calculated' => [
                    'price' => 8.395,
                    'basisPrice' => 8.395,
                    'nettoPriceNotRounded' => 8.395,
                    'sum' => 8.395,
                    'nettoPrice' => 8.395,
                    'nettoBasisPrice' => 8.395,
                    'nettoSubSum' => 8.395,
                    'nettoSum' => 8.395,
                    'vatArray' => ['vat' => 19, 'sum' => 1.5951, 'text' => 'VAT 19%'],
                    'isNetto' => true,
                    'isEuVat' => false
                ]
            ]],
            'calculations' => [
                'sum' => 9.99,
                'subSum' => 8.395,
                'grandSubSum' => 9.9901,
                'nettoSubSum' => 8.395,
                'nettoSum' => 8.395,
                'vatArray' => [19 => ['vat' => 19, 'sum' => 1.5951, 'text' => 'VAT 19%']],
                'vatText' => [19 => 'VAT 19%'],
                'isNetto' => true,
                'isEuVat' => false,
                'currencyData' => Defaults::getCurrency()->toArray()
            ],
            'priceFactors' => []
        ];
    }
}
