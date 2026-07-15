<?php

namespace QUITests\ERP\Money;

use ReflectionClass;
use QUI;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Discount\Discount;
use QUI\ERP\Money\Price;

/**
 * Class BruttoUserTest
 */
class PriceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(Discount::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/QUI/ERP/Discount/Discount.php';
        }
    }

    protected function createPriceObject(float|int $value = 10.0): Price
    {
        $Reflection = new ReflectionClass(Price::class);
        $PriceObject = $Reflection->newInstanceWithoutConstructor();

        $currency = $this->createMock(Currency::class);
        $currency->method('getCode')->willReturn('EUR');
        $currency->method('format')->willReturn('10.00 EUR');

        $Reflection->getProperty('price')->setValue($PriceObject, $value);
        $Reflection->getProperty('Currency')->setValue($PriceObject, $currency);
        $Reflection->getProperty('isMinimalPrice')->setValue($PriceObject, false);
        $Reflection->getProperty('discounts')->setValue($PriceObject, []);

        return $PriceObject;
    }

    public function testValidatePrice()
    {
        $this->assertEquals(
            10,
            QUI\ERP\Money\Price::parsePrice(10)
        );

        $this->assertEquals(
            -10,
            QUI\ERP\Money\Price::parsePrice(-10)
        );

        $this->assertEquals(
            '10.00',
            QUI\ERP\Money\Price::parsePrice(10)
        );

        $this->assertEquals(
            '10.10',
            QUI\ERP\Money\Price::parsePrice(10.1)
        );

        $this->assertEquals(
            '5.99',
            QUI\ERP\Money\Price::parsePrice(5.99)
        );

        $this->assertEquals(
            '-5.99',
            QUI\ERP\Money\Price::parsePrice(-5.99)
        );

        $this->assertEquals(
            '5.9999',
            QUI\ERP\Money\Price::parsePrice(5.9999)
        );
    }

    public function testValidatePriceWithLocaleSeparators(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('getDecimalSeparator')->willReturn(',');
        $Locale->method('getGroupingSeparator')->willReturn('.');

        $this->assertSame(1234.56, Price::parsePrice('1.234,56', $Locale));
        $this->assertSame(-1234.56, Price::parsePrice('-1.234,56', $Locale));
        $this->assertSame(1234.0, Price::parsePrice('1.234', $Locale));
        $this->assertSame(12.5, Price::parsePrice('Preis: 12,50 €', $Locale));
    }

    public function testValidatePriceWithLegacyDecimalSeparatorArray(): void
    {
        $testCases = [
            [[','], '.', '1.234,56', 1234.56],
            [['.'], ',', '1,234.56', 1234.56],
            [[','], '.', '1.000.000,00', 1000000.0],
            [['.'], ',', '1,000,000.00', 1000000.0],
            [[','], '.', '-1.000.000,00', -1000000.0],
            [['.'], ',', '-1,000,000.00', -1000000.0]
        ];

        foreach ($testCases as [$decimalSeparator, $groupingSeparator, $price, $expected]) {
            $Locale = $this->createMock(QUI\Locale::class);
            $Locale->method('getDecimalSeparator')->willReturn($decimalSeparator);
            $Locale->method('getGroupingSeparator')->willReturn($groupingSeparator);

            $this->assertSame($expected, Price::parsePrice($price, $Locale));
        }
    }

    public function testValidatePriceWithEmptyAndInvalidValues(): void
    {
        $this->assertNull(Price::parsePrice(''));
        $this->assertNull(Price::parsePrice('foo-bar'));
    }

    public function testValidatePriceAcceptsPriceInstance(): void
    {
        $PriceObject = $this->createPriceObject(4.56789);

        $this->assertSame(4.56789, Price::parsePrice($PriceObject));
    }

    public function testDeprecatedValidatePriceDelegatesToParsePrice(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('getDecimalSeparator')->willReturn(',');
        $Locale->method('getGroupingSeparator')->willReturn('.');

        $this->assertSame(
            Price::parsePrice('1.234,56', $Locale),
            Price::validatePrice('1.234,56', $Locale)
        );
    }

    public function testNullConstructorPriceIsExposedAsZero(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Price = new Price(null, $Currency);

        $this->assertSame(0.0, $Price->getPrice());
        $this->assertSame(0.0, $Price->value());
        $this->assertSame(0.0, $Price->getValue());
    }

    public function testMinimalPriceStateCanBeToggled(): void
    {
        $Reflection = new ReflectionClass(Price::class);
        $PriceObject = $Reflection->newInstanceWithoutConstructor();

        $this->assertFalse($PriceObject->isMinimalPrice());
        $PriceObject->enableMinimalPrice();
        $this->assertTrue($PriceObject->isMinimalPrice());
        $PriceObject->disableMinimalPrice();
        $this->assertFalse($PriceObject->isMinimalPrice());
    }

    public function testDisplayAndArrayAccessors(): void
    {
        $PriceObject = $this->createPriceObject(12.3456);
        $Reflection = new ReflectionClass(Price::class);

        $currency = $this->createMock(Currency::class);
        $currency->method('format')->willReturn('12.3456 EUR');
        $currency->method('getCode')->willReturn('EUR');

        $Reflection->getProperty('Currency')->setValue($PriceObject, $currency);
        $PriceObject->enableMinimalPrice();

        $this->assertSame(12.3456, $PriceObject->getPrice());
        $this->assertSame(12.3456, $PriceObject->value());
        $this->assertSame(12.3456, $PriceObject->getValue());
        $this->assertSame('12.3456 EUR', $PriceObject->getDisplayPrice());
        $this->assertSame($currency, $PriceObject->getCurrency());
        $this->assertSame([], $PriceObject->getDiscounts());

        $array = $PriceObject->toArray();
        $this->assertSame(12.3456, $array['price']);
        $this->assertSame('EUR', $array['currency']);
        $this->assertSame('12.3456 EUR', $array['display']);
        $this->assertTrue($array['isMinimalPrice']);
    }

    public function testAddDiscountPreventsDuplicatesAndChecksCombinability(): void
    {
        $PriceObject = $this->createPriceObject(10);

        $Discount1 = $this->createMock(Discount::class);
        $Discount1->method('getId')->willReturn(1);
        $Discount1->method('canCombinedWith')->willReturn(false);

        $Discount2 = $this->createMock(Discount::class);
        $Discount2->method('getId')->willReturn(2);

        $PriceObject->addDiscount($Discount1);
        $this->assertCount(1, $PriceObject->getDiscounts());

        $PriceObject->addDiscount($Discount1);
        $this->assertCount(1, $PriceObject->getDiscounts());

        $this->expectException(QUI\Exception::class);
        $PriceObject->addDiscount($Discount2);
    }
}
