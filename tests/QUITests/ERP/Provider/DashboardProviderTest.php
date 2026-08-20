<?php

namespace QUITests\ERP\Provider;

use PHPUnit\Framework\TestCase;
use QUI\Dashboard\DashboardInterface;
use QUI\ERP\Dashboard\ErpDashboard;
use QUI\ERP\Provider\DashboardProvider;
use QUI\Locale;

class DashboardProviderTest extends TestCase
{
    public function testProviderAndBoardExposeLocalizedContracts(): void
    {
        $Locale = $this->createMock(Locale::class);
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key): string => $package . ':' . $key
        );

        $Provider = new DashboardProvider();
        self::assertSame('quiqqer/erp:dashboard.provider.title', $Provider->getTitle($Locale));
        self::assertSame([], DashboardProvider::getCards());

        $boards = DashboardProvider::getBoards();
        self::assertCount(1, $boards);
        self::assertInstanceOf(DashboardInterface::class, $boards[0]);
        self::assertSame('quiqqer/erp:dashboard.erp.title', $boards[0]->getTitle($Locale));
        self::assertSame([
            'package/quiqqer/erp/bin/backend/controls/dashboard/cards/GlobalProcessIdList'
        ], $boards[0]->getCards());
        self::assertSame('', $boards[0]->getJavaScriptControl());
    }
}
