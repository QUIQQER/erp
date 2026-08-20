<?php

namespace QUITests\ERP\Api;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Cache\Manager as CacheManager;
use QUI\Config;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Api\AbstractErpProvider;
use QUI\ERP\Api\Coordinator;
use QUI\Locale;
use Stash\Interfaces\ItemInterface;
use Stash\Pool;

require_once __DIR__ . '/Fixtures/CoordinatorFixtureProvider.php';

class CoordinatorTest extends TestCase
{
    private ?Config $originalCacheConfig;
    private ?Pool $originalStash;
    private ?Locale $originalLocale;

    protected function setUp(): void
    {
        $this->originalCacheConfig = CacheManager::$Config;
        $this->originalStash = CacheManager::$Stash;
        $this->originalLocale = QUI::$Locale;

        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturn(false);
        CacheManager::$Config = $Config;
    }

    protected function tearDown(): void
    {
        CacheManager::$Config = $this->originalCacheConfig;
        CacheManager::$Stash = $this->originalStash;
        QUI::$Locale = $this->originalLocale;
    }

    public function testProviderCollectionFiltersInvalidClassesAndAggregatesContracts(): void
    {
        $this->useCachedProviders([
            [CoordinatorFixtureProvider::class, \stdClass::class, 'Missing\\Provider']
        ]);

        $Coordinator = Coordinator::getInstance();
        $providers = $Coordinator->getErpApiProvider();

        self::assertCount(1, $providers);
        self::assertInstanceOf(CoordinatorFixtureProvider::class, $providers[0]);
        self::assertSame(['invoice', 'order'], $Coordinator->getNumberRanges());
        self::assertSame([
            ['title' => 'A mail'],
            ['title' => 'Z mail']
        ], $Coordinator->getMailTextsList());
    }

    public function testMenuItemsAreBuiltAndSortedByPriorityThenLocaleText(): void
    {
        $providerItem = $this->cacheItem([CoordinatorFixtureProvider::class], false);
        $menuItem = $this->cacheItem(null, true);
        $menuItem->expects(self::once())->method('set')->with(self::isType('array'));
        $menuItem->expects(self::once())->method('save');

        $Stash = $this->createMock(Pool::class);
        $Stash->method('getItem')->willReturnCallback(
            static function (string $key) use ($providerItem, $menuItem): ItemInterface {
                return str_contains($key, 'erp/provider/menuItems') ? $menuItem : $providerItem;
            }
        );
        CacheManager::$Stash = $Stash;

        $Locale = $this->createMock(Locale::class);
        $Locale->method('get')->willReturnCallback(
            static fn(string $group, string $key): string => $key
        );
        QUI::$Locale = $Locale;

        $result = Coordinator::getInstance()->getMenuItems();

        self::assertSame(
            ['priority', 'alpha', 'zeta', 'invalid-locale'],
            array_column($result['items'], 'name')
        );
        self::assertSame(
            ['child-alpha', 'child-zeta'],
            array_column($result['items'][1]['items'], 'name')
        );
    }

    /** @param array<mixed> $providers */
    private function useCachedProviders(array $providers): void
    {
        $Stash = $this->createMock(Pool::class);
        $Stash->method('getItem')->willReturn($this->cacheItem($providers, false));
        CacheManager::$Stash = $Stash;
    }

    private function cacheItem(mixed $value, bool $isMiss): ItemInterface
    {
        $Item = $this->createMock(ItemInterface::class);
        $Item->method('get')->willReturn($value);
        $Item->method('isMiss')->willReturn($isMiss);

        return $Item;
    }
}
