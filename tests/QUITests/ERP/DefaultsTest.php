<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\Defaults;
use QUI\ERP\Utils\User;
use QUI\Package\Manager;
use QUI\Package\Package;
use QUI\Projects\Media;
use QUI\Projects\Media\Image;
use QUI\Projects\Project;
use ReflectionClass;
use ReflectionProperty;

class DefaultsTest extends TestCase
{
    private ?Manager $originalPackageManager;

    /** @var array<string, int|string|null> */
    private array $originalTimestampFormat;

    /** @var array<string, string> */
    private array $originalDateFormat;

    private ?bool $originalUserRelatedCurrency;

    protected function setUp(): void
    {
        $this->originalPackageManager = QUI::$PackageManager;
        $Reflection = new ReflectionClass(Defaults::class);
        $this->originalTimestampFormat = $Reflection->getProperty('timestampFormat')->getValue();
        $this->originalDateFormat = $Reflection->getProperty('dateFormat')->getValue();
        $this->originalUserRelatedCurrency = $Reflection->getProperty('userRelatedCurrency')->getValue();
        $Reflection->getProperty('timestampFormat')->setValue([]);
        $Reflection->getProperty('dateFormat')->setValue([]);
        $Reflection->getProperty('userRelatedCurrency')->setValue(null);
    }

    protected function tearDown(): void
    {
        QUI::$PackageManager = $this->originalPackageManager;
        $Reflection = new ReflectionClass(Defaults::class);
        $Reflection->getProperty('timestampFormat')->setValue($this->originalTimestampFormat);
        $Reflection->getProperty('dateFormat')->setValue($this->originalDateFormat);
        $Reflection->getProperty('userRelatedCurrency')->setValue($this->originalUserRelatedCurrency);
    }

    public function testGetPrecisionReturnsEightInPhpUnitRuntime(): void
    {
        $this->assertSame(8, Defaults::getPrecision());
    }

    public function testGetTimestampFormatUsesCachedValue(): void
    {
        $Reflection = new ReflectionClass(Defaults::class);
        $property = $Reflection->getProperty('timestampFormat');
        $property->setValue(['de' => 'dd.MM.yyyy, HH:mm:ss']);

        $this->assertSame('dd.MM.yyyy, HH:mm:ss', Defaults::getTimestampFormat('de'));
    }

    public function testGetDateFormatUsesCachedValue(): void
    {
        $Reflection = new ReflectionClass(Defaults::class);
        $property = $Reflection->getProperty('dateFormat');
        $property->setValue(['de' => 'dd.MM.yyyy']);

        $this->assertSame('dd.MM.yyyy', Defaults::getDateFormat('de'));
    }

    public function testPhpUnitRuntimeDetectionIsTrue(): void
    {
        $Reflection = new ReflectionClass(Defaults::class);
        $method = $Reflection->getMethod('isPhpUnitRuntime');
        $this->assertTrue($method->invoke(null));
    }

    public function testConfigurationAndFormattingValuesComeFromErpPackage(): void
    {
        $this->useConfig([
            'company' => [
                'name' => 'Example GmbH',
                'street' => 'Main Street 7',
                'zipCode' => '12345',
                'city' => 'Example City'
            ],
            'timestampFormat' => ['de' => 'yyyy-MM-dd HH:mm:ss'],
            'dateFormat' => ['de' => 'yyyy-MM-dd']
        ]);

        self::assertSame('Example GmbH', Defaults::conf('company', 'name'));
        self::assertFalse(Defaults::conf('company', 'missing'));
        self::assertSame('yyyy-MM-dd HH:mm:ss', Defaults::getTimestampFormat('de'));
        self::assertSame('yyyy-MM-dd', Defaults::getDateFormat('de'));
        self::assertSame(
            'Example GmbH - Main Street 7 - 12345 Example City',
            Defaults::getShortAddress()
        );
    }

    public function testInvalidDateFormatsUseDocumentedDefaults(): void
    {
        $this->useConfig([
            'timestampFormat' => ['de' => null],
            'dateFormat' => ['de' => '   ']
        ]);

        self::assertSame('MMM dd, yyyy, hh:mm:ss', Defaults::getTimestampFormat('de'));
        self::assertSame('MMM dd, yyyy', Defaults::getDateFormat('de'));
    }

    public function testGlobalBruttoNettoStatusReflectsTaxConfiguration(): void
    {
        $this->useConfig(['shop' => ['isNetto' => true]]);
        self::assertSame(User::IS_NETTO_USER, Defaults::getBruttoNettoStatus());

        $this->useConfig(['shop' => ['isNetto' => false]]);
        self::assertSame(User::IS_BRUTTO_USER, Defaults::getBruttoNettoStatus());
    }

    public function testMissingPackagesProduceSafeConfigurationDefaults(): void
    {
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willThrowException(new QUI\Exception('missing'));
        QUI::$PackageManager = $Manager;

        self::assertFalse(Defaults::conf('company', 'name'));
        self::assertSame(User::IS_BRUTTO_USER, Defaults::getBruttoNettoStatus());
        self::assertSame('MMM dd, yyyy, hh:mm:ss', Defaults::getTimestampFormat('xx'));
        self::assertSame('MMM dd, yyyy', Defaults::getDateFormat('xx'));
    }

    public function testUserCurrencyUsesDefaultCurrencyWhenSwitchingIsDisabled(): void
    {
        $this->useConfig(['general' => ['userRelatedCurrency' => false]]);
        $DefaultCurrency = Defaults::getCurrency();

        self::assertSame($DefaultCurrency, Defaults::getUserCurrency());
        self::assertSame($DefaultCurrency, Defaults::getUserCurrency());
    }

    public function testDateFormatsResolveTheCurrentLocaleWhenLanguageIsOmitted(): void
    {
        $language = QUI::getLocale()->getCurrent();
        $this->useConfig([
            'timestampFormat' => [$language => 'yyyy/MM/dd HH:mm'],
            'dateFormat' => [$language => 'yyyy/MM/dd']
        ]);

        self::assertSame('yyyy/MM/dd HH:mm', Defaults::getTimestampFormat());
        self::assertSame('yyyy/MM/dd', Defaults::getDateFormat());
    }

    public function testLogoFallsBackToTheStandardProjectMedia(): void
    {
        $this->useConfig(['general' => ['logo' => false]]);
        $Logo = $this->createMock(Image::class);
        $Media = $this->createMock(Media::class);
        $Media->method('getLogoImage')->willReturn($Logo);
        $Project = $this->createMock(Project::class);
        $Project->method('getMedia')->willReturn($Media);

        $StandardProject = new ReflectionProperty(QUI\Projects\Manager::class, 'Standard');
        $originalStandardProject = $StandardProject->getValue();

        try {
            $StandardProject->setValue(null, $Project);
            self::assertSame($Logo, Defaults::getLogo());
        } finally {
            $StandardProject->setValue(null, $originalStandardProject);
        }
    }

    /** @param array<string, array<string, mixed>> $values */
    private function useConfig(array $values): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturnCallback(
            static fn(string $section, string $key): mixed => $values[$section][$key] ?? false
        );
        $Config->method('getValue')->willReturnCallback(
            static fn(string $section, string $key): mixed => $values[$section][$key] ?? false
        );
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        QUI::$PackageManager = $Manager;
    }
}
