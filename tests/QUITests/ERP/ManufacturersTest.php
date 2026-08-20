<?php

namespace QUITests\ERP;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\Manufacturers;
use QUI\Groups\Group;
use QUI\Groups\Manager as GroupsManager;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Package\Manager as PackageManager;
use QUI\Package\Package;
use QUI\Users\Address;
use QUI\Users\Manager as UsersManager;
use QUI\Users\SystemUser;
use QUI\Users\User;
use ReflectionProperty;

require_once __DIR__ . '/Fixtures/ManufacturerUsersManagerFixture.php';

class ManufacturersTest extends TestCase
{
    private ?PackageManager $originalPackageManager;
    private ?GroupsManager $originalGroups;
    private ?UsersManager $originalUsers;
    private ?QUI\Locale $originalLocale;
    private Connection $originalConnection;
    private Connection $Connection;
    /** @var array<string, mixed> */
    private array $originalAjaxCallables;
    /** @var array<string, mixed> */
    private array $originalAjaxPermissions;

    protected function setUp(): void
    {
        $this->originalPackageManager = QUI::$PackageManager;
        $this->originalGroups = QUI::$Groups;
        $this->originalUsers = QUI::$Users;
        $this->originalLocale = QUI::$Locale;
        $this->originalConnection = QUI::getDataBaseConnection();
        $this->Connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $this->Connection);
        QUI::getAjax();
        $this->originalAjaxCallables = (new ReflectionProperty(QUI\Ajax::class, 'callables'))->getValue();
        $this->originalAjaxPermissions = (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $this->originalConnection);
        $this->Connection->close();
        QUI::$PackageManager = $this->originalPackageManager;
        QUI::$Groups = $this->originalGroups;
        QUI::$Users = $this->originalUsers;
        QUI::$Locale = $this->originalLocale;
        (new ReflectionProperty(QUI\Ajax::class, 'callables'))->setValue(null, $this->originalAjaxCallables);
        (new ReflectionProperty(QUI\Ajax::class, 'permissions'))->setValue(null, $this->originalAjaxPermissions);
    }

    public function testConfiguredManufacturerGroupIsNormalizedWithoutProductsPackage(): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->with('manufacturers', 'groupId')->willReturn('42');
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(PackageManager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        $Manager->method('isInstalled')->with('quiqqer/products')->willReturn(false);
        QUI::$PackageManager = $Manager;

        self::assertSame([42], Manufacturers::getManufacturerGroupIds());
    }

    public function testSearchUsesOnlyIsolatedSqliteTables(): void
    {
        $usersTable = QUI::getDBTableName('users');
        $addressesTable = QUI::getDBTableName('users_address');
        $this->Connection->executeStatement(
            'CREATE TABLE ' . $this->Connection->quoteIdentifier($usersTable)
            . ' (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, username TEXT,'
            . ' usergroup TEXT, active INTEGER, regdate INTEGER, address INTEGER)'
        );
        $this->Connection->executeStatement(
            'CREATE TABLE ' . $this->Connection->quoteIdentifier($addressesTable)
            . ' (id INTEGER PRIMARY KEY, company TEXT)'
        );
        $this->Connection->insert($addressesTable, ['id' => 1, 'company' => 'Fixture GmbH']);
        $this->Connection->insert($usersTable, [
            'id' => 9001,
            'firstname' => 'Maria',
            'lastname' => 'Maker',
            'email' => 'maria@example.test',
            'username' => 'maker',
            'usergroup' => ',42,',
            'active' => 1,
            'regdate' => strtotime('2026-01-01'),
            'address' => 1
        ]);
        $this->configureGroup(42);

        $rows = Manufacturers::search(['search' => 'Fixture', 'limit' => '0,20']);
        $count = Manufacturers::search(['search' => 'Fixture'], true);

        self::assertCount(1, $rows);
        self::assertSame(9001, $rows[0]['id']);
        self::assertSame(1, $count);
        self::assertSame(1, (int)$this->Connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($usersTable)
        ));
    }

    public function testGridMappingResolvesGroupsAndAddressFallbacks(): void
    {
        $GroupA = $this->createMock(Group::class);
        $GroupA->method('getName')->willReturn('Suppliers');
        $GroupB = $this->createMock(Group::class);
        $GroupB->method('getName')->willReturn('Manufacturers');
        $Groups = $this->createMock(GroupsManager::class);
        $Groups->method('get')->willReturnMap([[42, $GroupA], [7, $GroupB]]);
        QUI::$Groups = $Groups;

        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'firstname' => 'Address',
                'lastname' => 'Name',
                'company' => 'Address GmbH',
                default => null
            }
        );
        $Address->method('getText')->willReturn('Street 1, Berlin');
        $Address->method('getMailList')->willReturn(['address@example.test']);
        $User = $this->createMock(User::class);
        $User->method('getStandardAddress')->willReturn($Address);
        $Users = $this->createMock(UsersManager::class);
        $Users->method('get')->with(9001)->willReturn($User);
        QUI::$Users = $Users;

        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('getCurrent')->willReturn('en');
        $Locale->method('getLocalesByLang')->willReturn(['en_US']);
        QUI::$Locale = $Locale;

        $result = Manufacturers::parseListForGrid([[
            'id' => '9001',
            'active' => 1,
            'username' => 'maker',
            'firstname' => '',
            'lastname' => '',
            'company' => '',
            'email' => '',
            'regdate' => strtotime('2026-01-01'),
            'usergroup' => ',42,7,'
        ]]);

        self::assertSame(9001, $result[0]['id']);
        self::assertSame('Address', $result[0]['firstname']);
        self::assertSame('Name', $result[0]['lastname']);
        self::assertSame('Address GmbH', $result[0]['company']);
        self::assertSame('address@example.test', $result[0]['email']);
        self::assertSame('Manufacturers, Suppliers', $result[0]['usergroup_display']);
        self::assertStringContainsString('Street 1, Berlin', $result[0]['address_display']);
    }

    public function testCreateManufacturerPopulatesAddressAndOnlyAllowedGroups(): void
    {
        $this->configureGroup(42);

        $SystemUser = $this->createMock(QUI\Users\SystemUser::class);
        $SystemUser->method('isSU')->willReturn(true);
        $Address = $this->createMock(Address::class);
        $Address->expects(self::exactly(9))->method('setAttribute');
        $Address->expects(self::once())->method('addMail')->with('supplier@example.test');
        $Address->expects(self::once())->method('save');

        $Manufacturer = $this->createMock(User::class);
        $Manufacturer->method('getUUID')->willReturn('manufacturer-fixture-42');
        $Manufacturer->method('getStandardAddress')->willReturn($Address);
        $Manufacturer->method('getAttribute')->willReturn(null);
        $Manufacturer->expects(self::exactly(3))->method('setAttribute');
        $Manufacturer->expects(self::once())->method('addToGroup')->with(42);
        $Manufacturer->expects(self::once())->method('save')->with($SystemUser);
        $Manufacturer->expects(self::once())->method('setPassword')->with(self::isType('string'), $SystemUser);
        $Manufacturer->expects(self::once())->method('activate')->with('', $SystemUser);

        $Users = new ManufacturerUsersManagerFixture($SystemUser, $Manufacturer);
        QUI::$Users = $Users;

        $PermissionUser = new ReflectionProperty(QUI\Permissions\Permission::class, 'User');
        $originalPermissionUser = $PermissionUser->getValue();

        try {
            $PermissionUser->setValue(null, $SystemUser);
            $result = Manufacturers::createManufacturer('acme-manufacturer', [
                'firstname' => 'Ada',
                'lastname' => 'Supplier',
                'company' => 'ACME Manufacturing',
                'street_no' => 'Factory Road 1',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'email' => 'supplier@example.test'
            ], [42, 999]);
        } finally {
            $PermissionUser->setValue(null, $originalPermissionUser);
        }

        self::assertSame($Manufacturer, $result);
        self::assertSame('manufacturer-fixture-42', $result->getUUID());
        self::assertSame('acme-manufacturer', $Users->createdUsername);
        self::assertSame($SystemUser, $Users->creatingUser);
    }

    public function testManufacturerSearchEndpointReturnsEmptyGridFromIsolatedSqlite(): void
    {
        $usersTable = QUI::getDBTableName('users');
        $addressesTable = QUI::getDBTableName('users_address');
        $this->Connection->executeStatement(
            'CREATE TABLE ' . $this->Connection->quoteIdentifier($usersTable)
            . ' (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, username TEXT,'
            . ' usergroup TEXT, active INTEGER, regdate INTEGER, address INTEGER)'
        );
        $this->Connection->executeStatement(
            'CREATE TABLE ' . $this->Connection->quoteIdentifier($addressesTable)
            . ' (id INTEGER PRIMARY KEY, company TEXT)'
        );
        $this->configureGroup(42);

        $search = $this->endpoint(
            'manufacturers/search.php',
            'package_quiqqer_erp_ajax_manufacturers_search',
            ['params']
        );
        $result = $search(json_encode([
            'search' => 'missing manufacturer',
            'limit' => '0,20'
        ], JSON_THROW_ON_ERROR));

        self::assertSame(0, $result['total']);
        self::assertSame([], $result['data']);
        self::assertSame(0, (int)$this->Connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->Connection->quoteIdentifier($usersTable)
        ));
    }

    public function testManufacturerGroupsEndpointMapsConfiguredGroups(): void
    {
        $this->configureGroup(42);
        $Group = $this->createMock(Group::class);
        $Group->method('getUUID')->willReturn('42');
        $Group->method('getName')->willReturn('Manufacturers');
        $Groups = $this->createMock(GroupsManager::class);
        $Groups->method('get')->with(42)->willReturn($Group);
        QUI::$Groups = $Groups;

        $getGroups = $this->endpoint(
            'manufacturers/create/getGroups.php',
            'package_quiqqer_erp_ajax_manufacturers_create_getGroups',
            []
        );

        self::assertSame([[
            'id' => '42',
            'name' => 'Manufacturers'
        ]], $getGroups());
    }

    public function testNewManufacturerEndpointPreservesDuplicateUsernameError(): void
    {
        $SystemUser = $this->createMock(SystemUser::class);
        $SystemUser->method('isSU')->willReturn(true);
        $Users = new ManufacturerUsersManagerFixture(
            $SystemUser,
            $this->createMock(User::class),
            true
        );
        QUI::$Users = $Users;
        $PermissionUser = new ReflectionProperty(QUI\Permissions\Permission::class, 'User');
        $originalPermissionUser = $PermissionUser->getValue();
        $create = $this->endpoint(
            'manufacturers/create/newManufacturer.php',
            'package_quiqqer_erp_ajax_manufacturers_create_newManufacturer',
            ['manufacturerId', 'address', 'groupIds']
        );

        try {
            $PermissionUser->setValue(null, $SystemUser);
            $create('existing-manufacturer', '{}', '[]');
            self::fail('Duplicate manufacturer usernames must be rejected.');
        } catch (QUI\ERP\Exception $Exception) {
            self::assertNotSame('', $Exception->getMessage());
            self::assertFalse($Users->createdUsername);
        } finally {
            $PermissionUser->setValue(null, $originalPermissionUser);
        }
    }

    private function configureGroup(int $groupId): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->with('manufacturers', 'groupId')->willReturn($groupId);
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(PackageManager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        $Manager->method('isInstalled')->with('quiqqer/products')->willReturn(false);
        QUI::$PackageManager = $Manager;
    }

    /** @param list<string> $parameters */
    private function endpoint(string $file, string $name, array $parameters): Closure
    {
        require dirname(__DIR__, 3) . '/ajax/' . $file;
        $callables = QUI\Ajax::getRegisteredCallables();

        self::assertArrayHasKey($name, $callables);
        self::assertSame($parameters, $callables[$name]['params']);

        return $callables[$name]['callable'];
    }
}
