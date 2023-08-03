<?php

namespace QUITests\ERP\Money;

use QUI;

/**
 * Class BruttoUserTest
 */
class PriceTest extends \PHPUnit_Framework_TestCase
{
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
}
