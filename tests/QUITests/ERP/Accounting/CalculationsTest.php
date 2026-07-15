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
}
