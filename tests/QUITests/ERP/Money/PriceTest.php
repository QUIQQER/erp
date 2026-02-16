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
            QUI\ERP\Money\Price::validatePrice(10)
        );

        $this->assertEquals(
            -10,
            QUI\ERP\Money\Price::validatePrice(-10)
        );

        $this->assertEquals(
            '10.00',
            QUI\ERP\Money\Price::validatePrice(10)
        );

        $this->assertEquals(
            '10.10',
            QUI\ERP\Money\Price::validatePrice(10.1)
        );

        $this->assertEquals(
            '5.99',
            QUI\ERP\Money\Price::validatePrice(5.99)
        );

        $this->assertEquals(
            '-5.99',
            QUI\ERP\Money\Price::validatePrice(-5.99)
        );

        $this->assertEquals(
            '5.9999',
            QUI\ERP\Money\Price::validatePrice(5.9999)
        );
    }

    public function testValidatePriceWithLocaleSeparators(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('getDecimalSeparator')->willReturn(',');
        $Locale->method('getGroupingSeparator')->willReturn('.');

        $this->assertSame(1234.56, Price::validatePrice('1.234,56', $Locale));
        $this->assertSame(-1234.56, Price::validatePrice('-1.234,56', $Locale));
        $this->assertSame(1234.0, Price::validatePrice('1.234', $Locale));
    }

    public function testValidatePriceWithEmptyAndInvalidValues(): void
    {
        $this->assertNull(Price::validatePrice(''));
        $this->assertNull(Price::validatePrice('foo-bar'));
    }

    public function testValidatePriceAcceptsPriceInstance(): void
    {
        $PriceObject = $this->createPriceObject(4.56789);

        $this->assertSame(4.56789, Price::validatePrice($PriceObject));
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
