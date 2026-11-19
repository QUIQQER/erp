<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Accounting\PriceFactors\Factor;
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

    public function testEnteringManualModeDoesNotRecalculateOrReplaceTheSnapshot(): void
    {
        $List = new ArticleList($this->snapshot());
        $beforeTotals = $List->getCalculations();
        $beforeArticle = $List->getArticles()[0]->toArray();

        $List->setCalculationMode(CalculationMode::MANUAL);

        self::assertSame($beforeTotals, $List->toArray()['calculations']);
        self::assertSame($beforeArticle, $List->toArray()['articles'][0]);
        self::assertSame(CalculationMode::MANUAL, $List->getArticles()[0]->getCalculationMode());
    }

    public function testIndependentManualGrossAmountsSurviveSerializationAndReloading(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = 'manual';
        $gross = [
            'sum' => 9.99,
            'quantity_sum' => 9.9901,
            'unitPrice' => 9.990123,
            'calculated' => ['sum' => 9.99, 'basisPrice' => 9.990123]
        ];
        $snapshot['articles'][0]['calculated']['bruttoCalculated'] = $gross;
        $List = new ArticleList($snapshot);
        $Reloaded = new ArticleList(json_decode($List->serialize(), true, 512, JSON_THROW_ON_ERROR));

        foreach ([$List, $Reloaded, $Reloaded->toUniqueList()] as $Copy) {
            $position = $Copy->toArray()['articles'][0]['calculated'];
            self::assertSame($gross, $position['bruttoCalculated']);
            self::assertSame(8.395, $position['nettoSum']);
            self::assertSame(1.5951, $position['vatArray']['sum']);
        }
    }

    public function testReturningToAutomaticDropsTheOldManualGrossAmounts(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = 'manual';
        $snapshot['articles'][0]['calculated']['bruttoCalculated'] = ['sum' => 123.4567];
        $List = new ArticleList($snapshot);
        $List->setCalculationMode(CalculationMode::AUTOMATIC);
        $List->setCalculationMode(CalculationMode::MANUAL);

        self::assertArrayNotHasKey('bruttoCalculated', $List->toArray()['articles'][0]['calculated']);
    }

    public function testManualAmountsSurviveCustomerChangesGettersAndRecalculation(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = 'manual';
        // Deliberately inconsistent: manual values belong to the user and must not be corrected.
        $snapshot['calculations']['sum'] = 42.1234;
        $snapshot['calculations']['vatSum'] = 0.123456;
        $User = $this->createMock(\QUI\Interfaces\Users\User::class);
        $List = new ArticleList($snapshot, $User);
        $before = $List->toArray();
        $Calc = $this->createMock(Calc::class);
        $Calc->expects(self::never())->method('calcArticleList');
        $Calc->expects(self::never())->method('calcArticlePrice');

        $List->setUser($this->createMock(\QUI\Interfaces\Users\User::class));
        $List->calc($Calc);
        $List->recalculate($Calc);
        $Article = $List->getArticles()[0];
        $Article->setUser($User);
        $Article->calc($Calc);

        self::assertSame(8.395, $Article->getPrice()->value());
        self::assertSame(8.395, $Article->getSum()->value());
        self::assertSame($before, $List->toArray());
        self::assertSame($before, $List->toUniqueList()->toArray());
        self::assertSame($List->serialize(), ArticleListUnique::unserialize($List->serialize())->serialize());
    }

    public function testDirectCalcEntryPointsReturnManualValuesIncludingCallbacks(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['calculationMode'] = 'manual';
        $List = new ArticleList($snapshot);
        $Article = $List->getArticles()[0];
        // No initialized user/tax context: the manual path must not enter the automatic calculator.
        $Calc = (new \ReflectionClass(Calc::class))->newInstanceWithoutConstructor();
        $totals = null;
        $position = null;

        self::assertSame($List, $Calc->calcArticleList($List));
        $Calc->calcArticleList($List, static function (array $data) use (&$totals): void {
            $totals = $data;
        });
        self::assertSame(8.395, $Calc->calcArticlePrice($Article)->value());
        $Calc->calcArticlePrice($Article, static function (array $data) use (&$position): void {
            $position = $data;
        });

        self::assertSame($snapshot['calculations'], $totals);
        self::assertSame($Article->toArray()['calculated'], $position);
    }

    public function testAddedAndReplacedArticlesInheritTheManualMode(): void
    {
        $snapshot = $this->snapshot();
        $List = new ArticleList($snapshot);
        $List->setCalculationMode(CalculationMode::MANUAL);
        $Added = new Article($snapshot['articles'][0]);
        $Replacement = new Article($snapshot['articles'][0]);

        $List->addArticle($Added);
        $List->replaceArticle($Replacement, 0);

        foreach ([$Added, $Replacement] as $Article) {
            $Article->setUser($this->createMock(\QUI\Interfaces\Users\User::class));
            self::assertSame(CalculationMode::MANUAL, $Article->getCalculationMode());
            self::assertSame(8.395, $Article->getSum()->value());
        }

        self::assertSame($snapshot['calculations'], $List->getCalculations());
    }

    public function testManualPriceFactorsKeepTheirAmountsAndDoNotRecalculateTotals(): void
    {
        $List = new ArticleList($this->snapshot());
        $List->setCalculationMode(CalculationMode::MANUAL);
        $totals = $List->getCalculations();
        $Factor = new Factor([
            'title' => 'Manual surcharge', 'description' => '',
            'sum' => 1.2345, 'sumFormatted' => '',
            'nettoSum' => 0.9876, 'nettoSumFormatted' => '',
            'visible' => 1, 'value' => 0.2469, 'vat' => 19
        ]);
        $before = $Factor->toArray();

        $List->addPriceFactor($Factor);

        self::assertSame($before, $List->getPriceFactors()->getFactor(0)->toArray());
        self::assertSame($totals, $List->getCalculations());
    }

    public function testSwitchingBackToAutomaticResumesCalculation(): void
    {
        $snapshot = $this->snapshot();
        $List = new ArticleList($snapshot);
        $List->setCalculationMode(CalculationMode::MANUAL);
        $List->setCalculationMode(CalculationMode::AUTOMATIC);
        $Calc = $this->createMock(Calc::class);
        $totals = $snapshot['calculations'];
        $totals['sum'] = 123.456;
        $Calc->expects(self::once())->method('calcArticleList')->willReturnCallback(
            static function (ArticleList $List, callable $callback) use ($totals): ArticleList {
                $callback($totals);
                return $List;
            }
        );

        $List->calc($Calc);

        self::assertSame(123.456, $List->getCalculations()['sum']);
        self::assertSame(CalculationMode::AUTOMATIC, $List->getArticles()[0]->getCalculationMode());
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
