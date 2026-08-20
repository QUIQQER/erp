<?php

namespace QUITests\ERP\Accounting;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Currency\Currency;
use QUI\Interfaces\Users\User as UserInterface;

class ArticleTest extends TestCase
{
    public function testStoredArticleSnapshotExposesAllBusinessValues(): void
    {
        $Currency = $this->currency();
        $User = $this->createMock(UserInterface::class);
        $Article = new Article([
            'id' => 73,
            'uuid' => 'article-73',
            'productSetParentUuid' => 'set-parent',
            'articleNo' => 'SKU-73',
            'gtin' => '4012345678901',
            'title' => 'Stored consulting package',
            'description' => 'A reproducible stored article snapshot',
            'unitPrice' => 100,
            'nettoPriceNotRounded' => 99.995,
            'quantity' => 2,
            'quantityUnit' => ['id' => '', 'title' => 'hours'],
            'vat' => 19,
            'position' => 3.5,
            'discount' => json_encode([
                'value' => 10,
                'type' => Calc::CALCULATION_PERCENTAGE
            ], JSON_THROW_ON_ERROR),
            'displayPrice' => false,
            'customFields' => ['costCenter' => 'CONSULTING'],
            'customData' => ['origin' => 'fixture'],
            'calculated' => [
                'price' => 90.0,
                'basisPrice' => 100.0,
                'nettoPriceNotRounded' => 99.995,
                'sum' => 180.0,
                'nettoPrice' => 90.0,
                'nettoBasisPrice' => 100.0,
                'nettoSubSum' => 200.0,
                'nettoSum' => 180.0,
                'vatArray' => [19 => ['sum' => 34.2]],
                'isEuVat' => false,
                'isNetto' => true
            ]
        ]);
        $Article->setCurrency($Currency);
        $Article->getDiscount()?->setCurrency($Currency);

        self::assertSame(73, $Article->getId());
        self::assertSame('article-73', $Article->getUuid());
        self::assertSame('set-parent', $Article->getProductSetParentUuid());
        self::assertSame('SKU-73', $Article->getArticleNo());
        self::assertSame('4012345678901', $Article->getGTIN());
        self::assertSame('Stored consulting package', $Article->getTitle());
        self::assertSame('A reproducible stored article snapshot', $Article->getDescription());
        self::assertSame(100.0, $Article->getUnitPrice()->value());
        self::assertSame(99.995, $Article->getUnitPriceUnRounded()->value());
        self::assertSame(180.0, $Article->getSum()->value());
        self::assertSame(180.0, $Article->getPrice()->value());
        self::assertSame(19.0, $Article->getVat());
        self::assertSame(2, $Article->getQuantity());
        self::assertSame('', $Article->getQuantityUnit());
        self::assertFalse($Article->displayPrice());
        self::assertNull($Article->getUser());
        self::assertSame($Currency, $Article->getCurrency());
        self::assertTrue($Article->hasDiscount());
        self::assertSame(10.0, $Article->getDiscount()?->getValue());
        self::assertSame('CONSULTING', $Article->getCustomField('costCenter'));
        self::assertNull($Article->getCustomField('missing'));
        self::assertSame(['costCenter' => 'CONSULTING'], $Article->getCustomFields());
        self::assertSame(['origin' => 'fixture'], $Article->getCustomData());

        $data = $Article->toArray();
        self::assertSame(3.5, $data['position']);
        self::assertSame(180.0, $data['calculated']['sum']);
        self::assertSame('set-parent', $Article->getView()->getAttribute('productSetParentUuid'));

        $Article->setUser($User);
        self::assertSame($User, $Article->getUser());
    }

    public function testArticleDefaultsAndDiscountTypeFallbackAreStable(): void
    {
        $Article = new Article([
            'vat' => 0,
            'calculated' => [
                'price' => 0,
                'basisPrice' => 0,
                'sum' => 0,
                'nettoPrice' => 0,
                'nettoBasisPrice' => 0,
                'nettoSum' => 0,
                'vatArray' => [],
                'isEuVat' => false,
                'isNetto' => true
            ]
        ]);
        $Article->setCurrency($this->currency());
        $Article->setDiscount(5, 999);

        self::assertSame(0, $Article->getId());
        self::assertNull($Article->getUuid());
        self::assertNull($Article->getProductSetParentUuid());
        self::assertSame('', $Article->getArticleNo());
        self::assertSame('', $Article->getGTIN());
        self::assertSame('', $Article->getTitle());
        self::assertSame('', $Article->getDescription());
        self::assertSame(1, $Article->getQuantity());
        self::assertSame(Calc::CALCULATION_COMPLEMENT, $Article->getDiscount()?->getCalculation());
    }

    public function testArticleViewFormatsStoredFieldsWithExplicitCurrency(): void
    {
        $language = \QUI::getLocale()->getCurrent();
        $Article = new Article([
            'id' => 74,
            'title' => 'View article',
            'unitPrice' => 25,
            'quantity' => 1,
            'quantityUnit' => ['id' => '', 'title' => 'piece'],
            'vat' => 19,
            'customFields' => [
                ['ignored' => true],
                [
                    'title' => 'Engraving',
                    'custom_calc' => [
                        'value' => 5,
                        'valueText' => [$language => 'Engraving'],
                        'displayDiscounts' => true,
                        'calculation' => Calc::CALCULATION_COMPLEMENT
                    ]
                ]
            ],
            'calculated' => [
                'price' => 25.0,
                'basisPrice' => 25.0,
                'sum' => 25.0,
                'nettoPrice' => 25.0,
                'nettoBasisPrice' => 25.0,
                'nettoSum' => 25.0,
                'vatArray' => [19 => ['sum' => 4.75]],
                'isEuVat' => false,
                'isNetto' => true
            ]
        ]);
        $Currency = $this->currency();
        $Article->setCurrency($Currency);
        $View = $Article->getView();
        $View->setCurrency($Currency);
        $View->setPosition(7);

        self::assertSame('', $View->getQuantityUnit());
        self::assertSame(7.0, $View->getPosition());
        self::assertSame($Currency, $View->getCurrency());
        self::assertSame('25.00 EUR', $View->getPrice());
        self::assertTrue($View->displayPrice());
        self::assertIsString($View->getImageUrl());

        $fields = $View->getCustomFields();
        self::assertCount(1, $fields);
        self::assertSame('Engraving (+5.00 EUR)', $fields[0]['custom_calc']['valueText']);
    }

    private function currency(): Currency
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Currency->method('getPrecision')->willReturn(2);
        $Currency->method('format')->willReturnCallback(
            static fn(float|int $amount): string => number_format((float)$amount, 2, '.', '') . ' EUR'
        );

        return $Currency;
    }
}
