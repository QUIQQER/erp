<?php

namespace QUITests\ERP\Accounting\PriceFactors;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\PriceFactors\Factor;
use QUI\ERP\Exception;

class FactorTest extends TestCase
{
    protected function createFactor(array $data = []): Factor
    {
        return new Factor(array_merge([
            'identifier' => 'default',
            'title' => 'Default',
            'description' => 'Default description',
            'sum' => 119.0,
            'sumFormatted' => '119.00 EUR',
            'nettoSum' => 100.0,
            'nettoSumFormatted' => '100.00 EUR',
            'visible' => 1,
            'value' => 119.0
        ], $data));
    }

    public function testConstructorThrowsExceptionIfRequiredFieldIsMissing(): void
    {
        $this->expectException(Exception::class);

        new Factor([
            'title' => 'Shipping',
            'description' => 'Shipping costs'
        ]);
    }

    public function testVatAndVatSumWithExplicitVat(): void
    {
        $Factor = $this->createFactor([
            'identifier' => 'shipping',
            'title' => 'Shipping',
            'description' => 'Shipping costs',
            'sum' => 11.9,
            'sumFormatted' => '11.90 EUR',
            'nettoSum' => 10.0,
            'nettoSumFormatted' => '10.00 EUR',
            'value' => 11.9,
            'vat' => 19
        ]);

        $this->assertSame(19.0, $Factor->getVat());
        $this->assertEqualsWithDelta(1.9, $Factor->getVatSum(), 0.0001);

        $data = $Factor->toArray();
        $this->assertSame('shipping', $data['identifier']);
        $this->assertSame(19.0, $data['vat']);
    }

    public function testEuVatStatusUsesNettoAndZeroVat(): void
    {
        $Factor = $this->createFactor([
            'title' => 'Service',
            'description' => 'Service fee'
        ]);

        $Factor->setEuVatStatus(true);

        $this->assertSame(100.0, $Factor->getSum());
        $this->assertSame(0, $Factor->getVat());
        $this->assertSame(0, $Factor->getVatSum());
    }

    public function testVatIsCalculatedFromSumWhenVatFieldMissing(): void
    {
        $Factor = $this->createFactor([
            'sum' => 119.0,
            'nettoSum' => 100.0
        ]);

        $this->assertSame(19.0, $Factor->getVat());
        $this->assertSame(19.0, $Factor->getVatSum());
    }

    public function testSettersAndGetters(): void
    {
        $Factor = $this->createFactor([
            'calculation' => 1,
            'calculation_basis' => 3,
            'valueText' => '10%'
        ]);

        $Factor->setSum(15.5);
        $Factor->setSumFormatted('15.50 EUR');
        $Factor->setNettoSum(13.0);
        $Factor->setNettoSumFormatted('13.00 EUR');
        $Factor->setValue(15.5);
        $Factor->setValueText('updated');

        $this->assertSame(15.5, $Factor->getSum());
        $this->assertSame('15.50 EUR', $Factor->getSumFormatted());
        $this->assertSame(13.0, $Factor->getNettoSum());
        $this->assertSame('13.00 EUR', $Factor->getNettoSumFormatted());
        $this->assertSame(15.5, $Factor->getValue());
        $this->assertSame(1, $Factor->getCalculation());
        $this->assertSame(3, $Factor->getCalculationBasis());
        $this->assertSame(1, $Factor->isVisible());
        $this->assertJson($Factor->toJSON());
    }
}
