<?php

namespace QUITests\ERP\Accounting\PriceFactors;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\PriceFactors\Factor;
use QUI\ERP\Accounting\PriceFactors\FactorList;

class FactorListTest extends TestCase
{
    protected function createFactor(string $identifier, int $value): Factor
    {
        return new Factor([
            'identifier' => $identifier,
            'title' => 'Factor ' . strtoupper($identifier),
            'description' => 'Desc ' . strtoupper($identifier),
            'sum' => $value,
            'sumFormatted' => $value . '.00 EUR',
            'nettoSum' => $value,
            'nettoSumFormatted' => $value . '.00 EUR',
            'visible' => 1,
            'value' => $value
        ]);
    }

    public function testCanCreateAndManipulateFactorList(): void
    {
        $FactorA = $this->createFactor('a', 10);
        $FactorB = $this->createFactor('b', 20);

        $List = new FactorList([$FactorA]);
        $this->assertSame(1, $List->count());
        $this->assertSame($FactorA, $List->getFactor(0));

        $List->addFactor($FactorB);
        $this->assertSame(2, $List->count());
        $this->assertSame($FactorB, $List->getFactor(1));

        $List->setFactor(0, $FactorB);
        $this->assertSame($FactorB, $List->getFactor(0));

        $List->removeFactor(0);
        $this->assertSame(1, $List->count());
        $this->assertSame($FactorB, $List->getFactor(0));
    }

    public function testCanBuildFromArrayAndSerialize(): void
    {
        $List = new FactorList([
            [
                'identifier' => 'shipping',
                'title' => 'Shipping',
                'description' => 'Shipping costs',
                'sum' => 5,
                'sumFormatted' => '5.00 EUR',
                'nettoSum' => 5,
                'nettoSumFormatted' => '5.00 EUR',
                'visible' => 1,
                'value' => 5
            ]
        ]);

        $data = $List->toArray();

        $this->assertCount(1, $data);
        $this->assertSame('shipping', $data[0]['identifier']);
        $this->assertJson($List->toJSON());
    }

    public function testIteratorAndMissingIndexes(): void
    {
        $FactorA = $this->createFactor('a', 1);
        $FactorB = $this->createFactor('b', 2);

        $List = new FactorList([$FactorA, $FactorB]);
        $iterated = [];

        foreach ($List as $Factor) {
            $iterated[] = $Factor->getIdentifier();
        }

        $this->assertSame(['a', 'b'], $iterated);
        $this->assertNull($List->getFactor(99));

        $List->setFactor(99, $FactorA);
        $List->removeFactor(99);

        $this->assertSame(2, $List->count());
    }
}
