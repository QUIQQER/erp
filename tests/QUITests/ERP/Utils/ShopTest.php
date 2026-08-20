<?php

namespace QUITests\ERP\Utils;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\Utils\Shop;
use QUI\Package\Manager;
use QUI\Package\Package;
use ReflectionProperty;

class ShopTest extends TestCase
{
    private ?Manager $originalPackageManager;

    protected function setUp(): void
    {
        $this->originalPackageManager = QUI::$PackageManager;
        $this->resetType();
    }

    protected function tearDown(): void
    {
        QUI::$PackageManager = $this->originalPackageManager;
        $this->resetType();
    }

    public function testSupportedBusinessTypesDriveAllPredicates(): void
    {
        $expectations = [
            'B2C' => [false, true, false, true, false, true],
            'B2B' => [true, false, true, false, true, false],
            'B2C-B2B' => [true, true, false, true, false, false],
            'B2B-B2C' => [true, true, true, false, false, false]
        ];

        foreach ($expectations as $type => $expected) {
            $this->useBusinessType($type);

            self::assertSame($type, Shop::getBusinessType());
            self::assertSame($expected, [
                Shop::isB2B(),
                Shop::isB2C(),
                Shop::isB2BPrioritized(),
                Shop::isB2CPrioritized(),
                Shop::isOnlyB2B(),
                Shop::isOnlyB2C()
            ]);
        }
    }

    public function testInvalidAndMissingTypesFallBackToB2C(): void
    {
        $this->useBusinessType('unsupported');
        self::assertSame('B2C', Shop::getBusinessType());

        $this->useBusinessType(false);
        self::assertSame('B2C', Shop::getBusinessType());
    }

    public function testShippingDetectionUsesInstalledPackageState(): void
    {
        $Package = $this->createMock(Package::class);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->with('quiqqer/shipping')->willReturn($Package);
        QUI::$PackageManager = $Manager;
        self::assertTrue(Shop::isShippingInstalled());

        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willThrowException(new QUI\Exception('missing'));
        QUI::$PackageManager = $Manager;
        self::assertFalse(Shop::isShippingInstalled());
    }

    private function useBusinessType(string|false $type): void
    {
        $this->resetType();
        $Config = $this->createMock(Config::class);
        $Config->method('get')->with('general', 'businessType')->willReturn($type);
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->with('quiqqer/erp')->willReturn($Package);
        QUI::$PackageManager = $Manager;
    }

    private function resetType(): void
    {
        (new ReflectionProperty(Shop::class, 'type'))->setValue(null, null);
    }
}
