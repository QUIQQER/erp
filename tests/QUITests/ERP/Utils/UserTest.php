<?php

namespace QUITests\ERP\Utils;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\Utils\User;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Locale;
use QUI\Package\Manager as PackageManager;
use QUI\Package\Package;
use QUI\Users\Address;
use QUI\Users\Manager;
use ReflectionClass;

class UserTest extends TestCase
{
    private ?Manager $originalUsers;
    private ?PackageManager $originalPackageManager;

    /** @var array<mixed> */
    private array $originalStatusCache;

    protected function setUp(): void
    {
        $this->originalUsers = QUI::$Users;
        $this->originalPackageManager = QUI::$PackageManager;

        $Reflection = new ReflectionClass(User::class);
        $property = $Reflection->getProperty('userBruttoNettoStatus');
        $this->originalStatusCache = $property->getValue();
        $property->setValue([]);
    }

    protected function tearDown(): void
    {
        QUI::$Users = $this->originalUsers;
        QUI::$PackageManager = $this->originalPackageManager;

        $Reflection = new ReflectionClass(User::class);
        $Reflection->getProperty('userBruttoNettoStatus')->setValue($this->originalStatusCache);
    }

    public function testIsNettoUserTrueWhenRuntimeStatusIsSet(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnMap([
            ['RUNTIME_NETTO_BRUTTO_STATUS', User::IS_NETTO_USER]
        ]);

        $this->assertTrue(User::isNettoUser($User));
    }

    public function testIsNettoUserFalseWhenRuntimeStatusIsBrutto(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnMap([
            ['RUNTIME_NETTO_BRUTTO_STATUS', User::IS_BRUTTO_USER]
        ]);

        $this->assertFalse(User::isNettoUser($User));
    }

    public function testFilterCustomerAttributesKeepsOnlyErpCustomerData(): void
    {
        self::assertSame(
            [
                'uuid' => 'customer-1',
                'email' => 'customer@example.test',
                'company' => 'Example GmbH',
                'quiqqer.erp.euVatId' => 'DE123'
            ],
            User::filterCustomerAttributes([
                'uuid' => 'customer-1',
                'email' => 'customer@example.test',
                'company' => 'Example GmbH',
                'quiqqer.erp.euVatId' => 'DE123',
                'password' => 'must-not-leak',
                'internalNote' => 'must-not-leak'
            ])
        );
    }

    public function testGetUserErpAddressUsesConfiguredAddressAndFallsBackToStandardAddress(): void
    {
        $standardAddress = $this->createMock(Address::class);
        $invoiceAddress = $this->createMock(Address::class);
        $Users = $this->createMock(Manager::class);
        $Users->method('isUser')->willReturn(true);
        QUI::$Users = $Users;

        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->with('quiqqer.erp.address')->willReturn(42);
        $User->method('getAddress')->with(42)->willReturn($invoiceAddress);
        $User->method('getStandardAddress')->willReturn($standardAddress);

        self::assertSame($invoiceAddress, User::getUserERPAddress($User));

        $UserWithoutInvoiceAddress = $this->createMock(UserInterface::class);
        $UserWithoutInvoiceAddress->method('getAttribute')->with('quiqqer.erp.address')->willReturn(null);
        $UserWithoutInvoiceAddress->method('getStandardAddress')->willReturn($standardAddress);

        self::assertSame($standardAddress, User::getUserERPAddress($UserWithoutInvoiceAddress));
    }

    public function testGetUserErpAddressRejectsNonUsers(): void
    {
        $Users = $this->createMock(Manager::class);
        $Users->method('isUser')->willReturn(false);
        QUI::$Users = $Users;

        $this->expectException(QUI\Exception::class);
        User::getUserERPAddress($this->createMock(UserInterface::class));
    }

    /**
     * @dataProvider salutationProvider
     * @param array<string, mixed> $attributes
     */
    public function testGetUserSalutationSelectsTheRelevantBusinessSalutation(
        array $attributes,
        bool $isCompany,
        string $expectedKey
    ): void {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => $attributes[$key] ?? null
        );
        $User->method('isCompany')->willReturn($isCompany);
        $User->method('getStandardAddress')->willReturn(null);

        $Locale = $this->createMock(Locale::class);
        $Locale->method('get')->willReturnCallback(
            static fn(string $group, string $key): string => $group . ':' . $key
        );

        self::assertSame(
            'quiqqer/erp:' . $expectedKey,
            User::getUserSalutation($User, $Locale)
        );
    }

    /** @return array<string, array{array<string, string>, bool, string}> */
    public static function salutationProvider(): array
    {
        return [
            'formal man' => [
                ['salutation' => 'Herr', 'lastname' => 'Muster'],
                false,
                'salutation.formal.mr_lastname'
            ],
            'formal woman' => [
                ['salutation' => 'Frau', 'lastname' => 'Muster'],
                false,
                'salutation.formal.ms_lastname'
            ],
            'company without contact' => [
                [],
                true,
                'salutation.formal.company_generic'
            ],
            'full name' => [
                ['firstname' => 'Alex', 'lastname' => 'Muster'],
                false,
                'salutation.neutral.full_name'
            ],
            'last name only' => [
                ['lastname' => 'Muster'],
                false,
                'salutation.neutral.generic'
            ],
            'first name only' => [
                ['firstname' => 'Alex'],
                false,
                'salutation.neutral.first_name'
            ],
            'no name' => [
                [],
                false,
                'salutation.neutral.generic'
            ]
        ];
    }

    public function testAddressContactOverridesStaleUserSalutationData(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'salutation' => 'Frau',
                'firstname' => 'Grace',
                'lastname' => 'Hopper',
                default => null
            }
        );

        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'salutation' => 'Herr',
                'firstname' => 'Alan',
                'lastname' => 'Turing',
                default => null
            }
        );
        $User->method('getStandardAddress')->willReturn($Address);
        $User->method('isCompany')->willReturn(false);

        $Locale = $this->createMock(Locale::class);
        $Locale->expects(self::once())
            ->method('get')
            ->with('quiqqer/erp', 'salutation.formal.ms_lastname', ['lastname' => 'Hopper'])
            ->willReturn('Sehr geehrte Frau Hopper');

        self::assertSame('Sehr geehrte Frau Hopper', User::getUserSalutation($User, $Locale));
    }

    public function testSetUserCurrentAddressPublishesCurrentCheckoutAddress(): void
    {
        $Address = $this->createMock(Address::class);
        $User = $this->createMock(UserInterface::class);
        $User->expects(self::once())
            ->method('setAttribute')
            ->with('CurrentAddress', $Address);

        User::setUserCurrentAddress($User, $Address);
    }

    public function testBusinessToBusinessConfigurationMakesRegularUsersNetto(): void
    {
        $this->configurePackages(['general' => ['businessType' => 'B2B']]);
        $User = $this->statusUser('b2b-user');

        self::assertSame(User::IS_NETTO_USER, User::getBruttoNettoUserStatus($User));
        self::assertSame(User::IS_NETTO_USER, User::getBruttoNettoUserStatus($User));
    }

    public function testSystemAndExplicitUserStatesTakePrecedence(): void
    {
        $this->configurePackages([]);
        $Users = $this->createMock(Manager::class);
        $Users->method('isSystemUser')->willReturn(true);
        QUI::$Users = $Users;

        self::assertSame(
            User::IS_NETTO_USER,
            User::getBruttoNettoUserStatus($this->statusUser('system-user'))
        );

        $RegularUsers = $this->createMock(Manager::class);
        $RegularUsers->method('isSystemUser')->willReturn(false);
        QUI::$Users = $RegularUsers;
        $Explicit = $this->statusUser('explicit-brutto', [
            'quiqqer.erp.isNettoUser' => (string)User::IS_BRUTTO_USER
        ]);
        self::assertSame(User::IS_BRUTTO_USER, User::getBruttoNettoUserStatus($Explicit));

        $TaxRegistered = $this->statusUser('tax-registered', [
            'quiqqer.erp.taxId' => 'DE-TAX-42'
        ]);
        self::assertSame(User::IS_NETTO_USER, User::getBruttoNettoUserStatus($TaxRegistered));
    }

    public function testMissingTaxPackageFallsBackToBrutto(): void
    {
        $ErpConfig = $this->createMock(Config::class);
        $ErpPackage = $this->createMock(Package::class);
        $ErpPackage->method('getConfig')->willReturn($ErpConfig);
        $Packages = $this->createMock(PackageManager::class);
        $Packages->method('getInstalledPackage')->willReturnCallback(
            static function (string $name) use ($ErpPackage): Package {
                if ($name === 'quiqqer/erp') {
                    return $ErpPackage;
                }

                throw new QUI\Exception('Package is unavailable');
            }
        );
        QUI::$PackageManager = $Packages;
        $Users = $this->createMock(Manager::class);
        $Users->method('isSystemUser')->willReturn(false);
        QUI::$Users = $Users;

        self::assertSame(
            User::IS_BRUTTO_USER,
            User::getBruttoNettoUserStatus($this->statusUser('without-tax-package'))
        );
    }

    public function testCompanyAddressHonoursForcedBruttoConfiguration(): void
    {
        $this->configurePackages([], ['shop' => ['companyForceBruttoPrice' => true]]);
        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->with('company')->willReturn('Fixture GmbH');
        $User = $this->statusUser('company-user');
        $User->method('getStandardAddress')->willReturn($Address);
        $Users = $this->createMock(Manager::class);
        $Users->method('isSystemUser')->willReturn(false);
        $Users->method('isUser')->willReturn(true);
        QUI::$Users = $Users;

        self::assertSame(User::IS_BRUTTO_USER, User::getBruttoNettoUserStatus($User));
    }

    /** @param array<string, mixed> $attributes */
    private function statusUser(string $uuid, array $attributes = []): UserInterface
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getUUID')->willReturn($uuid);
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => $attributes[$key] ?? null
        );

        return $User;
    }

    /**
     * @param array<string, array<string, mixed>> $erpValues
     * @param array<string, array<string, mixed>> $taxValues
     */
    private function configurePackages(array $erpValues, array $taxValues = []): void
    {
        $packages = [];

        foreach (['quiqqer/erp' => $erpValues, 'quiqqer/tax' => $taxValues] as $name => $values) {
            $Config = $this->createMock(Config::class);
            $Config->method('getValue')->willReturnCallback(
                static fn(string $section, string $key): mixed => $values[$section][$key] ?? false
            );
            $Package = $this->createMock(Package::class);
            $Package->method('getConfig')->willReturn($Config);
            $packages[$name] = $Package;
        }

        $Manager = $this->createMock(PackageManager::class);
        $Manager->method('getInstalledPackage')->willReturnCallback(
            static fn(string $name): Package => $packages[$name]
        );
        QUI::$PackageManager = $Manager;
    }
}
