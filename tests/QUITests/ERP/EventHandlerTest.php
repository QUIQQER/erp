<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\EventHandler;
use QUI\ERP\Utils\Shop;
use QUI\Groups\Group;
use QUI\Groups\Manager as GroupsManager;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Package\Manager;
use QUI\Package\Package;
use QUI\Smarty\Collector;
use QUI\Template;
use QUI\Users\Address;
use QUI\Users\Manager as UsersManager;
use ReflectionProperty;
use Smarty;
use Symfony\Component\HttpFoundation\Request;

class EventHandlerTest extends TestCase
{
    private ?Manager $originalPackageManager;
    private ?UsersManager $originalUsers;
    private ?Template $originalTemplate;
    private ?GroupsManager $originalGroups;
    private ?Request $originalRequest;

    protected function setUp(): void
    {
        $this->originalPackageManager = QUI::$PackageManager;
        $this->originalUsers = QUI::$Users;
        $this->originalTemplate = QUI::$Template;
        $this->originalGroups = QUI::$Groups;
        $this->originalRequest = QUI::$Request;
        (new ReflectionProperty(Shop::class, 'type'))->setValue(null, null);
    }

    protected function tearDown(): void
    {
        QUI::$PackageManager = $this->originalPackageManager;
        QUI::$Users = $this->originalUsers;
        QUI::$Template = $this->originalTemplate;
        QUI::$Groups = $this->originalGroups;
        QUI::$Request = $this->originalRequest;
        (new ReflectionProperty(Shop::class, 'type'))->setValue(null, null);
    }

    public function testAdminFooterPublishesStylesIconsAndLoader(): void
    {
        ob_start();
        EventHandler::onAdminLoadFooter();
        $output = (string)ob_get_clean();

        self::assertStringContainsString('payment-status.css', $output);
        self::assertStringContainsString('ERP_ENTITY_ICONS', $output);
        self::assertStringContainsString('quiqqer/erp/bin/load.js', $output);
    }

    public function testTemplateHeaderIsExtendedOnlyForConfiguredAreas(): void
    {
        $Template = $this->createMock(Template::class);
        $Template->expects(self::once())
            ->method('extendHeaderWithJavaScriptFile')
            ->with(URL_OPT_DIR . 'quiqqer/erp/bin/frontend.js');
        $this->useConfigs(['general' => [
            'customerRequestWindow' => 'checkout,registration',
            'businessType' => 'B2C-B2B'
        ]]);

        EventHandler::onTemplateGetHeader($Template);

        $Template = $this->createMock(Template::class);
        $Template->expects(self::never())->method('extendHeaderWithJavaScriptFile');
        $this->useConfigs(['general' => ['customerRequestWindow' => '']]);
        EventHandler::onTemplateGetHeader($Template);
    }

    public function testPackageSetupLeavesExistingManufacturerAndBankMigrationUntouched(): void
    {
        $saveCount = 0;
        $values = [
            'manufacturers' => ['groupId' => 8123],
            'bankAccounts' => ['isPatched' => 1]
        ];
        $Config = $this->config($values, $saveCount);
        $Package = $this->createMock(Package::class);
        $Package->method('getName')->willReturn('quiqqer/erp');
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        QUI::$PackageManager = $Manager;

        EventHandler::onPackageSetup($Package);
        self::assertSame(0, $saveCount);

        $Other = $this->createMock(Package::class);
        $Other->method('getName')->willReturn('vendor/other');
        EventHandler::onPackageSetup($Other);
        self::assertSame(0, $saveCount);
    }

    public function testBankMigrationMarksIncompleteLegacyDataAsHandled(): void
    {
        $values = [
            'bankAccounts' => ['isPatched' => 0],
            'company' => ['bankName' => 'Bank', 'bankIban' => '', 'bankBic' => 'BIC', 'name' => 'Example']
        ];
        $saveCount = 0;
        $Config = $this->config($values, $saveCount);
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        QUI::$PackageManager = $Manager;

        EventHandler::patchBankAccount();

        self::assertSame(1, $saveCount);
    }

    public function testBankMigrationCreatesConfiguredDefaultAccount(): void
    {
        $values = [
            'bankAccounts' => ['isPatched' => 0, 'accounts' => ''],
            'company' => [
                'bankName' => 'Fixture Bank',
                'bankIban' => 'DE02120300000000202051',
                'bankBic' => 'BYLADEM1001',
                'name' => 'Example GmbH'
            ]
        ];
        $saveCount = 0;
        $Config = $this->config($values, $saveCount);
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->with('quiqqer/erp')->willReturn($Package);
        QUI::$PackageManager = $Manager;

        EventHandler::patchBankAccount();

        $accounts = json_decode($values['bankAccounts']['accounts'], true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $accounts);
        $account = array_values($accounts)[0];
        self::assertSame('Fixture Bank', $account['name']);
        self::assertSame('DE02120300000000202051', $account['iban']);
        self::assertSame('Example GmbH', $account['accountHolder']);
        self::assertSame($account['id'], $values['company']['bankAccountId']);
        self::assertSame(1, $values['bankAccounts']['isPatched']);
        self::assertSame(2, $saveCount);
    }

    public function testPackageConfigSavePersistsOnlyAvailableLanguages(): void
    {
        $languages = QUI::availableLanguages();
        self::assertNotEmpty($languages);
        $language = $languages[0];
        $saved = [];
        $Config = $this->createMock(Config::class);
        $Config->method('setValue')->willReturnCallback(
            static function (string $section, ?string $key, mixed $value) use (&$saved): bool {
                $saved[$section][$key] = $value;
                return true;
            }
        );
        $Config->expects(self::once())->method('save');
        $Package = $this->createMock(Package::class);
        $Package->method('getName')->willReturn('quiqqer/erp');
        $Package->method('getConfig')->willReturn($Config);

        EventHandler::onPackageConfigSave($Package, [
            'timestampFormat' => [$language => 'yyyy-MM-dd HH:mm', 'xx' => 'ignored'],
            'dateFormat' => [$language => 'yyyy-MM-dd', 'xx' => 'ignored']
        ]);

        self::assertSame('yyyy-MM-dd HH:mm', $saved['timestampFormat'][$language]);
        self::assertSame('yyyy-MM-dd', $saved['dateFormat'][$language]);
        self::assertArrayNotHasKey('xx', $saved['timestampFormat']);
    }

    public function testUserSaveCleansAndStoresVatId(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->with('quiqqer.erp.euVatId')->willReturn(' DE 123 ');
        $User->expects(self::once())->method('setAttribute')->with('quiqqer.erp.euVatId', 'DE123');
        $Users = $this->createMock(UsersManager::class);
        $Users->method('isUser')->with($User)->willReturn(true);
        QUI::$Users = $Users;
        $this->useConfigs(['shop' => ['validateVatId' => false]], 'quiqqer/tax');

        EventHandler::onUserSave($User);
    }

    public function testSmartyFunctionsResolveObjectsArraysAndAssignments(): void
    {
        $Smarty = new Smarty();
        EventHandler::onSmartyInit($Smarty);
        self::assertArrayHasKey('erpGetPrefixedNumber', $Smarty->registered_plugins['function']);
        self::assertArrayHasKey('strtolower', $Smarty->registered_plugins['modifier']);

        $Entity = new class () {
            public function getPrefixedNumber(): string
            {
                return 'INV-42';
            }
        };
        self::assertSame('INV-42', EventHandler::getPrefixedNumber(['var' => $Entity], $Smarty));
        self::assertSame('ORD-7', EventHandler::getPrefixedNumber([
            'var' => ['prefixedNumber' => 'ORD-7']
        ], $Smarty));
        self::assertSame('LEGACY-9', EventHandler::getPrefixedNumber([
            'var' => ['id_str' => 'LEGACY-9']
        ], $Smarty));
        self::assertSame('OBJECT-11', EventHandler::getPrefixedNumber([
            'var' => new class () {
                public function getId(): string
                {
                    return 'OBJECT-11';
                }
            }
        ], $Smarty));
        self::assertSame('', EventHandler::getPrefixedNumber([
            'var' => ['hash' => 'missing-entity']
        ], $Smarty));
        self::assertSame('', EventHandler::getPrefixedNumber([
            'var' => 'missing-entity'
        ], $Smarty));
        self::assertSame('', EventHandler::getPrefixedNumber([], $Smarty));

        $Assignment = new class () {
            /** @var array<string, mixed> */
            public array $values = [];
            public function assign(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }
        };
        self::assertSame('', EventHandler::getPrefixedNumber([
            'var' => $Entity,
            'assign' => 'number'
        ], $Assignment));
        self::assertSame('INV-42', $Assignment->values['number']);
    }

    public function testFrontendCollectorsRenderAllProfileAndAddressExtensions(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->willReturnMap([['quiqqer.erp.euVatId', 'DE123']]);
        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->with('company')->willReturn('Example GmbH');
        $Users = $this->createMock(UsersManager::class);
        $Users->method('isUser')->with($User)->willReturn(true);
        QUI::$Users = $Users;

        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('fetch')->willReturnCallback(
            static fn(string $file): string => '<section>' . basename($file) . '</section>'
        );
        $Engine->expects(self::atLeast(6))->method('assign');
        $Template = $this->createMock(Template::class);
        $Template->method('getEngine')->willReturn($Engine);
        QUI::$Template = $Template;

        $this->useConfigs([
            'general' => ['businessType' => 'B2C-B2B'],
            'profile' => ['addressFields' => json_encode([], JSON_THROW_ON_ERROR)]
        ]);
        $Collector = $this->createMock(Collector::class);
        $Collector->expects(self::exactly(6))
            ->method('append')
            ->with(self::stringContains('<section>'));

        EventHandler::onFrontendUserCustomerBegin($Collector, $User, $Address);
        EventHandler::onFrontendUserDataMiddle($Collector, $User, $Address);
        EventHandler::onFrontendUserAddressCreateBegin($Collector, $User);
        EventHandler::onFrontendUserAddressCreateEnd($Collector, $User);
        EventHandler::onFrontendUserAddressEditBegin($Collector, $User, $Address);
        EventHandler::onFrontendUserAddressEditEnd($Collector, $User, $Address);
    }

    public function testDefaultManufacturerGroupIsCreatedAndPersistedOnce(): void
    {
        $values = ['manufacturers' => ['groupId' => null]];
        $saveCount = 0;
        $Config = $this->config($values, $saveCount);
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        $Manager->method('isInstalled')->with('quiqqer/products')->willReturn(false);
        QUI::$PackageManager = $Manager;

        $SystemUser = $this->createMock(QUI\Users\SystemUser::class);
        $Users = $this->createMock(UsersManager::class);
        $Users->method('getSystemUser')->willReturn($SystemUser);
        QUI::$Users = $Users;

        $Manufacturers = $this->createMock(Group::class);
        $Manufacturers->method('getUUID')->willReturn('manufacturer-group');
        $Manufacturers->expects(self::once())->method('activate');
        $Root = $this->createMock(Group::class);
        $Root->expects(self::once())
            ->method('createChild')
            ->with(self::isType('string'), $SystemUser)
            ->willReturn($Manufacturers);
        $Groups = $this->createMock(GroupsManager::class);
        $Groups->method('firstChild')->willReturn($Root);
        QUI::$Groups = $Groups;

        EventHandler::createDefaultManufacturerGroup();

        self::assertSame('manufacturer-group', $values['manufacturers']['groupId']);
        self::assertSame(1, $saveCount);
    }

    public function testFrontendUserSaveSanitizesCompanyAndTaxIdentifiers(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->expects(self::once())->method('setAttribute')->with('company', 'ACME GmbH');
        $Address->expects(self::once())->method('save');
        $User = $this->createMock(QUI\Users\User::class);
        $User->method('getStandardAddress')->willReturn($Address);
        $User->expects(self::exactly(2))->method('setAttribute')->willReturnCallback(
            static function (string $key, mixed $value): void {
                self::assertContains($key, ['quiqqer.erp.euVatId', 'quiqqer.erp.chUID']);
                self::assertStringNotContainsString('<', (string)$value);
            }
        );
        $Users = $this->createMock(UsersManager::class);
        $Users->method('isUser')->with($User)->willReturn(true);
        QUI::$Users = $Users;
        QUI::$Request = new Request([], [
            'data' => json_encode([
                'company' => '<b>ACME GmbH</b>',
                'vatId' => '',
                'chUID' => '<i>CHE-123</i>'
            ], JSON_THROW_ON_ERROR)
        ]);

        EventHandler::onUserSaveBegin($User);
    }

    public function testAddressSaveCopiesSubmittedVatIdBackToCustomer(): void
    {
        $Address = $this->createMock(Address::class);
        $User = $this->createMock(QUI\Users\User::class);
        $User->expects(self::once())->method('setAttribute')->with('quiqqer.erp.euVatId', '');
        $User->expects(self::once())->method('save');
        $Users = $this->createMock(UsersManager::class);
        $Users->method('isUser')->with($User)->willReturn(true);
        QUI::$Users = $Users;
        QUI::$Request = new Request([], [
            'data' => json_encode(['vatId' => ''], JSON_THROW_ON_ERROR)
        ]);

        EventHandler::onUserAddressSave($Address, $User);
    }

    /** @param array<string, array<string, mixed>> $values */
    private function useConfigs(array $values, string $singlePackage = ''): void
    {
        $saveCount = 0;
        $Config = $this->config($values, $saveCount);
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturnCallback(
            static function (string $name) use ($Package, $singlePackage): Package {
                if ($singlePackage !== '' && $name !== $singlePackage) {
                    throw new QUI\Exception('Package not available');
                }

                return $Package;
            }
        );
        QUI::$PackageManager = $Manager;
    }

    /**
     * @param array<string, array<string, mixed>> $values
     */
    private function config(array &$values, int &$saveCount): Config
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturnCallback(
            static fn(string $section, string $key): mixed => $values[$section][$key] ?? false
        );
        $Config->method('getValue')->willReturnCallback(
            static fn(string $section, string $key): mixed => $values[$section][$key] ?? false
        );
        $Config->method('getSection')->willReturnCallback(
            static fn(string $section): array => $values[$section] ?? []
        );
        $Config->method('setValue')->willReturnCallback(
            static function (string $section, ?string $key, mixed $value) use (&$values): bool {
                self::assertNotNull($key);
                $values[$section][$key] = $value;
                return true;
            }
        );
        $Config->method('save')->willReturnCallback(static function () use (&$saveCount): void {
            $saveCount++;
        });

        return $Config;
    }
}
