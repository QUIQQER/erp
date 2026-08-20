<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\Debug;
use QUI\ERP\Packages\Exception as InstallerException;
use QUI\ERP\Packages\Installer;
use QUI\ERP\Provider\Requirements;
use QUI\ERP\Utils\Process as ProcessUtils;
use QUI\ERP\Utils\Sites;
use QUI\Locale;
use QUI\Package\Manager;
use QUI\Package\Package;
use ReflectionMethod;
use ReflectionProperty;

class UtilityBehaviorTest extends TestCase
{
    private ?Manager $originalPackageManager;
    private ?Debug $originalDebugInstance;

    protected function setUp(): void
    {
        $this->originalPackageManager = QUI::$PackageManager;
        $DebugInstance = new ReflectionProperty(Debug::class, 'Instance');
        $this->originalDebugInstance = $DebugInstance->getValue();
        $DebugInstance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        QUI::$PackageManager = $this->originalPackageManager;
        (new ReflectionProperty(Debug::class, 'Instance'))->setValue(null, $this->originalDebugInstance);
    }

    public function testLegalSiteHelpersReturnNullForMissingAndUntranslatedConfiguration(): void
    {
        $this->useErpConfig([
            'terms_and_conditions' => false,
            'revocation' => json_encode(['de' => '/widerruf'], JSON_THROW_ON_ERROR),
            'privacy_policy' => json_encode(['de' => '/datenschutz'], JSON_THROW_ON_ERROR)
        ]);
        $Locale = $this->createMock(Locale::class);
        $Locale->method('getCurrent')->willReturn('en');

        self::assertNull(Sites::getTermsAndConditions($Locale));
        self::assertNull(Sites::getRevocation($Locale));
        self::assertNull(Sites::getPrivacyPolicy($Locale));
    }

    public function testProcessInformationReturnsEmptyContractWhenOptionalPackagesAreMissing(): void
    {
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willThrowException(new QUI\Exception('not installed'));
        QUI::$PackageManager = $Manager;

        self::assertSame([], ProcessUtils::getProcessInformation('missing-process'));
    }

    public function testDebugLifecycleHonoursConfiguredStateAndSingletonContract(): void
    {
        $this->useErpConfig(['debug' => 1], 'general');
        $Debug = Debug::getInstance();

        self::assertSame($Debug, Debug::getInstance());

        $Debug->disable();
        $Debug->log('must remain disabled');
        self::assertSame(0, (new ReflectionProperty(Debug::class, 'debug'))->getValue($Debug));

        $Debug->enable();
        self::assertSame(1, (new ReflectionProperty(Debug::class, 'debug'))->getValue($Debug));
    }

    public function testInstallerPublishesSupportedPackagesAndRejectsUnknownPackages(): void
    {
        $Installer = new Installer();
        $packages = $Installer->getPackageList();

        self::assertContains('quiqqer/invoice', $packages);
        self::assertContains('quiqqer/order', $packages);
        self::assertContains('quiqqer/products', $packages);

        $Requirements = new ReflectionMethod(Installer::class, 'getPackageRequirements');
        self::assertSame(
            ['git@dev.quiqqer.com:quiqqer/tax.git', 'git@dev.quiqqer.com:quiqqer/areas.git'],
            $Requirements->invoke($Installer, 'quiqqer/tax')['server']
        );

        $this->expectException(InstallerException::class);
        $Installer->install('vendor/not-an-erp-package');
    }

    public function testEmptyRequirementProviderCanBeConstructed(): void
    {
        self::assertInstanceOf(Requirements::class, new Requirements());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function useErpConfig(array $values, string $section = 'sites'): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('getValue')->willReturnCallback(
            static fn(string $requestedSection, string $key): mixed =>
                $requestedSection === $section ? ($values[$key] ?? false) : false
        );
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->with('quiqqer/erp')->willReturn($Package);
        QUI::$PackageManager = $Manager;
    }
}
