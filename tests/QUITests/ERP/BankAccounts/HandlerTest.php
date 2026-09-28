<?php

namespace QUITests\ERP\BankAccounts;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\BankAccounts\Handler;
use QUI\Package\Manager;
use QUI\Package\Package;

class HandlerTest extends TestCase
{
    private ?Manager $originalPackageManager;

    /** @var array<string, mixed> */
    private array $sections;

    private int $saveCount = 0;

    protected function setUp(): void
    {
        $this->originalPackageManager = QUI::$PackageManager;
        $this->sections = [
            'bankAccounts' => ['accounts' => ''],
            'company' => ['bankAccountId' => '']
        ];
        $this->installPackageConfig();
    }

    protected function tearDown(): void
    {
        QUI::$PackageManager = $this->originalPackageManager;
    }

    public function testAddBankAccountRejectsEachMissingRequiredField(): void
    {
        $required = ['title', 'name', 'iban', 'bic', 'accountHolder'];
        $complete = array_fill_keys($required, 'value');

        foreach ($required as $field) {
            $data = $complete;
            unset($data[$field]);

            try {
                Handler::addBankAccount($data);
                self::fail('Missing required bank account data must be rejected.');
            } catch (QUI\Exception $Exception) {
                self::assertStringContainsString($field, $Exception->getMessage());
            }
        }

        self::assertSame(0, $this->saveCount);
    }

    public function testAddAndReadBankAccountsThroughConfiguration(): void
    {
        $created = Handler::addBankAccount([
            'title' => 'Main account',
            'name' => 'Example Bank',
            'iban' => 'DE02120300000000202051',
            'bic' => 'BYLADEM1001',
            'accountHolder' => 'Example GmbH',
            'default' => true,
            'creditorId' => 'DE98ZZZ09999999999'
        ]);

        self::assertIsInt($created['id']);
        self::assertSame('', $created['financialAccountNo']);
        self::assertSame(1, $this->saveCount);
        self::assertSame($created, Handler::getBankAccountById($created['id']));
        self::assertSame($created, Handler::getDefaultBankAccount());

        $this->sections['company']['bankAccountId'] = $created['id'];
        self::assertSame($created, Handler::getCompanyBankAccount());
        self::assertFalse(Handler::getBankAccountById($created['id'] + 1));
    }

    public function testEmptyAndNonDefaultListsReturnDocumentedFallbacks(): void
    {
        self::assertSame([], Handler::getList());
        self::assertFalse(Handler::getDefaultBankAccount());
        self::assertFalse(Handler::getCompanyBankAccount());

        $this->sections['bankAccounts']['accounts'] = json_encode([
            10001 => ['id' => 10001, 'default' => false]
        ], JSON_THROW_ON_ERROR);

        self::assertFalse(Handler::getDefaultBankAccount());
        self::assertSame(['id' => 10001, 'default' => false], Handler::getBankAccountById(10001));
    }

    public function testUpdatesArePersistentAndOnlyChangeTheSelectedAccount(): void
    {
        $data = array_fill_keys(['title', 'name', 'iban', 'bic', 'accountHolder'], 'Original');
        $first = Handler::addBankAccount($data + ['default' => true]);
        $second = Handler::addBankAccount($data);
        $second['title'] = 'Updated';
        $second['creditorId'] = 'Creditor ID';
        Handler::updateBankAccount($second['id'], $second);

        self::assertSame($first, Handler::getDefaultBankAccount());
        self::assertSame('Updated', Handler::getBankAccountById($second['id'])['title']);
        self::assertSame('Creditor ID', Handler::getBankAccountById($second['id'])['creditorId']);
        self::assertSame(3, $this->saveCount);

        $second['default'] = true;
        Handler::updateBankAccount($second['id'], $second);
        self::assertSame($second['id'], Handler::getDefaultBankAccount()['id']);
        self::assertFalse(Handler::getBankAccountById($first['id'])['default']);
        self::assertCount(2, Handler::getList());
    }

    public function testCreateDefaultAndDeletePersistWithoutChoosingAnotherAccount(): void
    {
        $data = array_fill_keys(['title', 'name', 'iban', 'bic', 'accountHolder'], 'Account');
        $first = Handler::addBankAccount($data + ['default' => true]);
        $second = Handler::addBankAccount($data + ['default' => true]);
        self::assertFalse(Handler::getBankAccountById($first['id'])['default']);
        self::assertSame($second, Handler::getDefaultBankAccount());

        Handler::deleteBankAccount($first['id']);
        self::assertSame($second, Handler::getDefaultBankAccount());
        self::assertFalse(Handler::getBankAccountById($first['id']));
        Handler::deleteBankAccount($second['id']);
        self::assertSame([], Handler::getList());
        self::assertFalse(Handler::getDefaultBankAccount());
        self::assertSame(4, $this->saveCount);
    }

    public function testInvalidUpdatesAndUnknownIdsDoNotChangeStoredAccounts(): void
    {
        $data = array_fill_keys(['title', 'name', 'iban', 'bic', 'accountHolder'], 'Account');
        $created = Handler::addBankAccount($data);

        foreach ([['title' => '  '], ['iban' => []], ['default' => 'false']] as $invalid) {
            try {
                Handler::updateBankAccount($created['id'], array_replace($data, $invalid));
                self::fail('Invalid account data must be rejected.');
            } catch (QUI\Exception) {
                self::assertSame($created, Handler::getBankAccountById($created['id']));
            }
        }

        foreach (['update', 'delete'] as $action) {
            try {
                if ($action === 'update') {
                    Handler::updateBankAccount(-1, $data);
                } else {
                    Handler::deleteBankAccount(-1);
                }

                self::fail('Unknown account IDs must be rejected.');
            } catch (QUI\Exception) {
                self::assertCount(1, Handler::getList());
            }
        }

        self::assertSame(1, $this->saveCount);
    }

    public function testGlobalSettingsDoNotDeclareTheIndependentlyManagedAccounts(): void
    {
        $settings = QUI\Utils\Text\XML::getConfigParamsFromXml(dirname(__DIR__, 4) . '/settings.xml');
        self::assertArrayNotHasKey('accounts', $settings['bankAccounts'] ?? []);
        self::assertArrayHasKey('bankAccountId', $settings['company']);
    }

    public function testWriteEndpointsEnforceSettingsPermissionAndReturnPersistedList(): void
    {
        $permissions = new \ReflectionProperty(QUI\Ajax::class, 'permissions');
        $originalPermissions = $permissions->getValue();
        $callablesProperty = new \ReflectionProperty(QUI\Ajax::class, 'callables');
        $originalCallables = $callablesProperty->getValue();

        try {
            require dirname(__DIR__, 4) . '/ajax/settings/bankAccounts/save.php';
            require dirname(__DIR__, 4) . '/ajax/settings/bankAccounts/delete.php';
            $callables = QUI\Ajax::getRegisteredCallables();
            $registeredPermissions = $permissions->getValue();
            $prefix = 'package_quiqqer_erp_ajax_settings_bankAccounts_';

            foreach (['save', 'delete'] as $action) {
                self::assertSame(
                    ['Permission::checkAdminUser', 'quiqqer.settings'],
                    $registeredPermissions[$prefix . $action]
                );
            }

            $save = $callables[$prefix . 'save']['callable'];
            $delete = $callables[$prefix . 'delete']['callable'];
            $data = array_fill_keys(['title', 'name', 'iban', 'bic', 'accountHolder'], 'Account');
            $list = $save('', json_encode($data));
            self::assertSame(Handler::getList(), $list);
            $id = array_key_first($list);
            $data['title'] = 'Edited via endpoint';
            $list = $save((string)$id, json_encode($data));
            self::assertSame('Edited via endpoint', $list[$id]['title']);
            self::assertSame([], $delete((string)$id));
            self::assertSame(3, $this->saveCount);
        } finally {
            $permissions->setValue(null, $originalPermissions);
            $callablesProperty->setValue(null, $originalCallables);
        }
    }

    private function installPackageConfig(): void
    {
        /** @var Config&MockObject $Config */
        $Config = $this->createMock(Config::class);
        $Config->method('getSection')->willReturnCallback(
            fn(string $section): array => $this->sections[$section] ?? []
        );
        $Config->method('get')->willReturnCallback(
            fn(string $section, string $key): mixed => $this->sections[$section][$key] ?? false
        );
        $Config->method('setValue')->willReturnCallback(
            function (string $section, ?string $key, mixed $value): bool {
                self::assertNotNull($key);
                $this->sections[$section][$key] = $value;
                return true;
            }
        );
        $Config->method('save')->willReturnCallback(function (): void {
            $this->saveCount++;
        });

        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);

        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->with('quiqqer/erp')->willReturn($Package);
        QUI::$PackageManager = $Manager;
    }
}
