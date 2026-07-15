<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Products\Utils\PriceFactor;

class ArticleListTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(PriceFactor::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/QUI/ERP/Products/Utils/PriceFactor.php';
        }
    }

    public function testKeepsArticleInterfaceInstance(): void
    {
        $Article = $this->createMock(ArticleInterface::class);
        $List = new ArticleList();

        $List->addArticle($Article);

        $this->assertSame($Article, $List->getArticle(0));
    }

    public function testDecimalVatArrayKeyIsUsedForPriceFactorCalculation(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->expects($this->once())
            ->method('getPrecision')
            ->willReturn(2);

        $List = new class () extends ArticleList {
            /**
             * @param array<string, array<mixed>> $vatArray
             */
            public function setPriceFactorCalculationState(
                array $vatArray,
                bool $isNetto,
                Currency $Currency
            ): void {
                $this->vatArray = $vatArray;
                $this->isNetto = $isNetto;
                $this->Currency = $Currency;
            }

            public function recalculate(?\QUI\ERP\Accounting\Calc $Calc = null): void
            {
            }
        };

        $List->setPriceFactorCalculationState(
            ['19.5' => ['sum' => 19.5]],
            false,
            $Currency
        );

        $PriceFactor = $this->createMock(PriceFactor::class);
        $PriceFactor->expects($this->once())
            ->method('getNettoSum')
            ->willReturn(100.0);
        $PriceFactor->expects($this->once())
            ->method('getVat')
            ->willReturn(false);
        $PriceFactor->expects($this->once())
            ->method('setVat')
            ->with(19.5);
        $PriceFactor->expects($this->once())
            ->method('setSum')
            ->with(119.5);
        $PriceFactor->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'title' => 'Decimal VAT factor',
                'description' => '',
                'sum' => 119.5,
                'sumFormatted' => '',
                'nettoSum' => 100.0,
                'nettoSumFormatted' => '',
                'visible' => 1,
                'value' => 119.5,
                'vat' => 19.5
            ]);

        $List->addPriceFactor($PriceFactor);

        $Factor = $List->getPriceFactors()->getFactor(0);

        $this->assertNotNull($Factor);
        $this->assertSame(19.5, $Factor->getVat());
        $this->assertSame(119.5, $Factor->getSum());
    }
}
