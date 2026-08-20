<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Config;
use QUI\ERP\Address;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Package\Manager;
use QUI\Package\Package;
use QUI\Template;

class AddressTest extends TestCase
{
    private ?Template $originalTemplate;
    private ?Manager $originalPackageManager;

    protected function setUp(): void
    {
        $this->originalTemplate = QUI::$Template;
        $this->originalPackageManager = QUI::$PackageManager;
    }

    protected function tearDown(): void
    {
        QUI::$Template = $this->originalTemplate;
        QUI::$PackageManager = $this->originalPackageManager;
    }

    public function testDisplayPublishesNormalizedCompanyAndContactAddressData(): void
    {
        $this->useContactPersonConfiguration(true);
        $User = $this->createMock(UserInterface::class);
        $User->method('isCompany')->willReturn(true);
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'firstname' => 'User first name',
                'lastname' => 'User last name',
                default => null
            }
        );
        $Address = new Address([
            'id' => 91,
            'uuid' => 'address-91',
            'company' => 'Example GmbH',
            'salutation' => 'Frau',
            'firstname' => '',
            'lastname' => '',
            'street_no' => 'Main Street 1',
            'zip' => '12345',
            'city' => 'Example City',
            'country' => 'DE',
            'contactPerson' => 'Grace Hopper',
            'suffix' => 'Building B'
        ], $User);

        $assigned = $this->useEngine();
        $html = $Address->getDisplay(['mail' => true, 'tel' => false]);
        $values = $assigned();

        self::assertSame('<address>rendered</address>', $html);
        self::assertTrue($values['isCompany']);
        self::assertSame('User first name', $values['firstname']);
        self::assertSame('User last name', $values['lastname']);
        self::assertSame('Grace Hopper', $values['contactPerson']);
        self::assertSame('Main Street 1', $values['street_no']);
        self::assertSame(['mail' => true, 'tel' => false], $values['options']);
        self::assertSame(91, $Address->getId());
        self::assertSame('address-91', $Address->getUUID());
    }

    public function testDisplaySuppressesDisabledOrNumericContactPerson(): void
    {
        $this->useContactPersonConfiguration(false);
        $Address = new Address([
            'isCompany' => 1,
            'company' => 'Example GmbH',
            'contactPerson' => 12345,
            'firstname' => 'Ada',
            'lastname' => 'Lovelace'
        ]);

        $assigned = $this->useEngine();
        $Address->getDisplay();
        $values = $assigned();

        self::assertTrue($values['isCompany']);
        self::assertSame('', $values['contactPerson']);
        self::assertSame('', $values['salutation']);
        self::assertSame('', $values['country']);
    }

    private function useContactPersonConfiguration(bool $enabled): void
    {
        $Config = $this->createMock(Config::class);
        $Config->method('get')->willReturnCallback(
            static fn(string $section, string $key): mixed =>
                $section === 'general' && $key === 'contactPersonOnAddress' ? $enabled : false
        );
        $Package = $this->createMock(Package::class);
        $Package->method('getConfig')->willReturn($Config);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getInstalledPackage')->willReturn($Package);
        QUI::$PackageManager = $Manager;
    }

    /** @return callable(): array<string, mixed> */
    private function useEngine(): callable
    {
        $assigned = [];
        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('assign')->willReturnCallback(
            static function (array $values) use (&$assigned): void {
                $assigned = $values;
            }
        );
        $Engine->method('fetch')->willReturn('<address>rendered</address>');
        $Template = $this->createMock(Template::class);
        $Template->method('getEngine')->with(true)->willReturn($Engine);
        QUI::$Template = $Template;

        return static function () use (&$assigned): array {
            return $assigned;
        };
    }
}
