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
