<?php

namespace QUITests\ERP\Provider;

use PHPUnit\Framework\TestCase;
use QUI\Cache\Manager as CacheManager;
use QUI\Config;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Provider\Erp;
use Stash\Interfaces\ItemInterface;
use Stash\Pool;

class ErpTest extends TestCase
{
    private ?Config $originalCacheConfig;
    private ?Pool $originalStash;

    protected function setUp(): void
    {
        $this->originalCacheConfig = CacheManager::$Config;
        $this->originalStash = CacheManager::$Stash;
    }

    protected function tearDown(): void
    {
        CacheManager::$Config = $this->originalCacheConfig;
        CacheManager::$Stash = $this->originalStash;
    }

    public function testErpWorkspaceItemsAreMappedIntoExtrasAndSettings(): void
    {
        $this->cacheWorkspaceMenu([[
            'name' => 'extras',
            'items' => [[
                'name' => 'erp',
                'items' => [
                    [
                        'icon' => 'fa fa-file-text-o',
                        'name' => 'invoices',
                        'locale' => ['quiqqer/invoice', 'menu.invoices'],
                        'require' => 'package/quiqqer/invoice/bin/backend/controls/InvoiceList'
                    ],
                    [
                        'icon' => 'fa fa-shopping-cart',
                        'name' => 'orders',
                        'locale' => ['quiqqer/order', 'menu.orders'],
                        'require' => 'package/quiqqer/order/bin/backend/controls/OrderList'
                    ]
                ]
            ]]
        ]]);
        $Map = new Map(['name' => 'erp-provider-map']);

        Erp::addMenuItems($Map);

        $data = $Map->toArray();
        self::assertSame(['extras', 'settings'], array_column($data['items'], 'name'));
        self::assertSame(['invoices', 'orders'], array_column($data['items'][0]['items'], 'name'));
        self::assertSame(
            'package/quiqqer/order/bin/backend/controls/OrderList',
            $data['items'][0]['items'][1]['require']
        );
        self::assertSame(
            'package/quiqqer/erp/bin/backend/utils/ErpMenuSettings',
            $data['items'][1]['require']
        );
    }

    public function testExistingMapParentsAreReusedAndMissingWorkspaceErpStopsCleanly(): void
    {
        $this->cacheWorkspaceMenu([['name' => 'extras', 'items' => []]]);
        $Map = new Map();
        $Extras = new Item(['name' => 'extras']);
        $Settings = new Item(['name' => 'settings']);
        $Map->appendChild($Extras);
        $Map->appendChild($Settings);

        Erp::addMenuItems($Map);

        $data = $Map->toArray();
        self::assertCount(2, $data['items']);
        self::assertSame([], $data['items'][0]['items']);
    }

    /** @param array<int, array<string, mixed>> $menu */
    private function cacheWorkspaceMenu(array $menu): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturn(false);
        CacheManager::$Config = $Config;

        $Item = $this->createMock(ItemInterface::class);
        $Item->method('get')->willReturn($menu);
        $Item->method('isMiss')->willReturn(false);
        $Stash = $this->createMock(Pool::class);
        $Stash->method('getItem')->willReturn($Item);
        CacheManager::$Stash = $Stash;
    }
}
