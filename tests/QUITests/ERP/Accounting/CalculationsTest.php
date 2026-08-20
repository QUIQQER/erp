<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\ArticleInterface;
use QUI\ERP\Accounting\Calculations;

class CalculationsTest extends TestCase
{
    public function testKeepsArticleInterfaceImplementations(): void
    {
        $Article = $this->createMock(ArticleInterface::class);
        $Calculations = new Calculations(
            [
                'sum' => 0,
                'subSum' => 0,
                'nettoSum' => 0,
                'nettoSubSum' => 0,
                'vatArray' => [],
                'vatText' => [],
                'isEuVat' => false,
                'isNetto' => true,
                'currencyData' => ['code' => 'EUR']
            ],
            [$Article]
        );

        $this->assertSame([$Article], $Calculations->getArticles());
    }

    public function testCalculationValuesExposeTotalsVatAndCurrencyFormatting(): void
    {
        $Article = $this->createMock(ArticleInterface::class);
        $Calculations = new Calculations($this->attributes(), [$Article, new \stdClass()]);

        self::assertSame(119.0, $Calculations->getSum()->value());
        self::assertSame(100.0, $Calculations->getSubSum()->value());
        self::assertSame(100.0, $Calculations->getNettoSum()->value());
        self::assertSame(90.0, $Calculations->getNettoSubSum()->value());
        self::assertSame(19.0, $Calculations->getVatSum()->value());
        self::assertSame($this->attributes()['vatArray'], $Calculations->getVatArray());
        self::assertSame([$Article], $Calculations->getArticles());

        $vat = $Calculations->getVat();
        self::assertCount(2, $vat);
        self::assertSame(19.0, $vat[0]->getVat());
        self::assertSame('VAT 19%', $vat[0]->getTitle());
        self::assertSame(17.1, $vat[0]->value());
        self::assertSame(7.0, $vat[1]->getVat());
        self::assertSame(1.9, $vat[1]->value());
    }

    public function testMissingRequiredCalculationAttributeIsRejected(): void
    {
        $attributes = $this->attributes();
        unset($attributes['vatText']);

        $this->expectException(\QUI\ERP\Exception::class);
        $this->expectExceptionMessage('Missing Calculations attribute');
        new Calculations($attributes);
    }

    /** @return array<string, mixed> */
    private function attributes(): array
    {
        return [
            'sum' => 119.0,
            'subSum' => 100.0,
            'nettoSum' => 100.0,
            'nettoSubSum' => 90.0,
            'vatArray' => [
                19 => ['sum' => 17.1, 'text' => 'VAT 19%'],
                7 => ['sum' => 1.9, 'text' => 'VAT 7%']
            ],
            'vatText' => [19 => 'VAT 19%', 7 => 'VAT 7%'],
            'isEuVat' => false,
            'isNetto' => true,
            'currencyData' => ['code' => 'EUR']
        ];
    }
}
