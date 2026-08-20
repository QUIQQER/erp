<?php

namespace QUITests\ERP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\User;
use QUI\Groups\Group;
use QUI\Groups\Manager as GroupsManager;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Users\Address;
use QUI\Users\Manager;

class UserValueTest extends TestCase
{
    private ?Manager $originalUsers;
    private ?GroupsManager $originalGroups;
    private ?QUI\Locale $originalLocale;

    protected function setUp(): void
    {
        $this->originalUsers = QUI::$Users;
        $this->originalGroups = QUI::$Groups;
        $this->originalLocale = QUI::$Locale;
    }

    protected function tearDown(): void
    {
        QUI::$Users = $this->originalUsers;
        QUI::$Groups = $this->originalGroups;
        QUI::$Locale = $this->originalLocale;
    }

    public function testValueUserExposesIdentityAddressAndBusinessContracts(): void
    {
        $User = $this->user();

        self::assertSame(501, $User->getId());
        self::assertSame('user-501', $User->getUUID());
        self::assertSame('user-501', $User->getUniqueId());
        self::assertSame('ada', $User->getUsername());
        self::assertSame('en', $User->getLang());
        self::assertSame('Ada Lovelace', $User->getName());
        self::assertSame('Analytical Engines Ltd.', $User->getInvoiceName());
        self::assertTrue($User->isCompany());
        self::assertTrue($User->isNetto());
        self::assertTrue($User->hasBruttoNettoStatus());
        self::assertSame(User::class, $User->getType());
        self::assertSame(0, $User->getStatus());
        self::assertSame('SUP-9', $User->getSupplierNo());
        self::assertStringEndsWith('CUST-7', $User->getCustomerNo());

        $Address = $User->getAddress();
        self::assertSame('Analytical Engines Ltd.', $Address->getAttribute('company'));
        self::assertSame($Address->getAttributes(), $User->getStandardAddress()->getAttributes());
        self::assertCount(1, $User->getAddressList());
        self::assertSame(['10', '20'], array_values($User->getGroups(false)));
        self::assertSame('DE123', $User->getAttribute('quiqqer.erp.euVatId'));
        self::assertSame('Ada', $User->getAttribute('firstname'));
        self::assertSame('Lovelace', $User->getAttribute('lastname'));
        self::assertSame('en', $User->getLocale()->getCurrent());
    }

    public function testValueUserDocumentsNonPersistentSecurityBehavior(): void
    {
        $User = $this->user();

        self::assertFalse($User->isSU());
        self::assertFalse($User->isInGroup(10));
        self::assertFalse($User->canUseBackend());
        self::assertTrue($User->activate());
        self::assertTrue($User->deactivate());
        self::assertTrue($User->disable());
        self::assertFalse($User->delete());
        self::assertFalse($User->getPermission('admin'));
        self::assertNull($User->addAddress());
        self::assertFalse($User->checkPassword('secret'));
        self::assertFalse($User->isDeleted());
        self::assertTrue($User->isActive());
        self::assertFalse($User->isOnline());
        self::assertFalse($User->hasAuthenticator('password'));
        self::assertSame([], $User->getAuthenticators());

        $User->logout();
        $User->save();
        $User->setGroups([10]);
        $User->setPassword('new password');
        $User->changePassword('new password', 'old password');
        $User->setCompanyStatus(false);
        $User->addToGroup(10);
        $User->removeGroup(10);
        $User->refresh();

        self::assertSame('user-501', $User->getUUID());
    }

    public function testAuthenticatorAccessRaisesConcreteNotFoundExceptions(): void
    {
        $User = $this->user();

        foreach (['getAuthenticator', 'enableAuthenticator', 'disableAuthenticator'] as $method) {
            try {
                $User->{$method}('missing');
                self::fail($method . ' must reject unavailable authenticators.');
            } catch (QUI\Users\Exception $Exception) {
                self::assertSame(404, $Exception->getCode());
            }
        }
    }

    public function testNeedlesAndMissingAttributesDescribeConversionRequirements(): void
    {
        self::assertSame(
            ['country', 'username', 'firstname', 'lastname', 'lang', 'isCompany'],
            User::getNeedles()
        );
        self::assertSame(
            ['country', 'lastname', 'lang', 'isCompany'],
            User::getMissingAttributes(['username' => 'ada', 'firstname' => 'Ada'])
        );
        self::assertSame([], User::getMissingAttributes(array_fill_keys(User::getNeedles(), 'value')));
    }

    public function testConvertSystemUserCopiesObservableProfileAndAddressData(): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getAttributes')->willReturn([
            'firstname' => 'Grace',
            'lastname' => 'Hopper',
            'company' => ''
        ]);
        $Source = $this->createMock(UserInterface::class);
        $Source->method('getCountry')->willReturn(null);
        $Source->method('getStandardAddress')->willReturn($Address);
        $Source->method('getAttributes')->willReturn(['department' => 'Compiler', 'extra' => 'removed']);
        $Source->method('getId')->willReturn(601);
        $Source->method('getUUID')->willReturn('user-601');
        $Source->method('getUsername')->willReturn('grace');
        $Source->method('getLang')->willReturn('en');
        $Source->method('isCompany')->willReturn(false);
        $Source->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'firstname' => 'Grace',
                'lastname' => 'Hopper',
                'email' => 'grace@example.test',
                'customerId' => 'CUST-8',
                'quiqqer.erp.isNettoUser' => 2,
                default => null
            }
        );
        $Users = $this->createMock(Manager::class);
        $Users->method('isNobodyUser')->willReturn(false);
        $Users->method('isSystemUser')->willReturn(false);
        QUI::$Users = $Users;

        $converted = User::convertUserToErpUser($Source);

        self::assertSame('user-601', $converted->getUUID());
        self::assertSame('Grace Hopper', $converted->getName());
        self::assertSame('Compiler', $converted->getAttribute('department'));
        self::assertFalse($converted->getAttribute('extra'));
        self::assertFalse($converted->isNetto());
    }

    public function testConvertUserDataRequiresUserAndAddressIdentifiers(): void
    {
        foreach ([[], ['uid' => 10]] as $data) {
            try {
                User::convertUserDataToErpUser($data);
                self::fail('Incomplete conversion data must be rejected.');
            } catch (QUI\ERP\Exception $Exception) {
                self::assertStringContainsString('Need ', $Exception->getMessage());
            }
        }
    }

    public function testConvertUserDataUsesTheExplicitCheckoutAddress(): void
    {
        $standardAddress = $this->createMock(Address::class);
        $standardAddress->method('getAttributes')->willReturn(['company' => 'Standard GmbH']);
        $checkoutAddress = $this->createMock(Address::class);
        $checkoutAddress->method('toJSON')->willReturn(json_encode([
            'id' => 88,
            'uuid' => 'checkout-address',
            'company' => 'Checkout GmbH',
            'firstname' => 'Katherine',
            'lastname' => 'Johnson'
        ], JSON_THROW_ON_ERROR));
        $Source = $this->createMock(QUI\Users\User::class);
        $Source->method('getAddress')->with(88)->willReturn($checkoutAddress);
        $Source->method('getStandardAddress')->willReturn($standardAddress);
        $Source->method('getAttributes')->willReturn([]);
        $Source->method('getId')->willReturn(701);
        $Source->method('getUUID')->willReturn('user-701');
        $Source->method('getUsername')->willReturn('katherine');
        $Source->method('getLang')->willReturn('en');
        $Source->method('isCompany')->willReturn(true);
        $Source->method('getAttribute')->willReturnCallback(
            static fn(string $key): mixed => match ($key) {
                'firstname' => 'Katherine',
                'lastname' => 'Johnson',
                'quiqqer.erp.isNettoUser' => 2,
                default => null
            }
        );
        $Users = $this->createMock(Manager::class);
        $Users->method('get')->willReturnCallback(
            static function (int|string $id) use ($Source): QUI\Users\User {
                self::assertContains($id, [701, 'user-701'], true);
                return $Source;
            }
        );
        $Users->method('isNobodyUser')->willReturn(false);
        $Users->method('isSystemUser')->willReturn(false);
        QUI::$Users = $Users;

        $converted = User::convertUserDataToErpUser(['uid' => 701, 'aid' => 88]);

        self::assertSame('Checkout GmbH', $converted->getAddress()->getAttribute('company'));
        self::assertSame('checkout-address', $converted->getAddress()->getUUID());
        self::assertSame('Katherine Johnson', $converted->getName());
    }

    public function testNameCountryAttributesAndGroupObjectsExposeStoredSnapshot(): void
    {
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key): string => $key === 'address.salutation.male' ? 'Mr.' : $key
        );
        QUI::$Locale = $Locale;
        $GroupA = $this->createMock(Group::class);
        $GroupB = $this->createMock(Group::class);
        $Groups = $this->createMock(GroupsManager::class);
        $Groups->method('get')->willReturnMap([['10', $GroupA], ['20', $GroupB]]);
        QUI::$Groups = $Groups;

        $User = new User([
            'id' => 702,
            'uuid' => 'user-702',
            'country' => ['code' => 'DE'],
            'username' => 'alan',
            'firstname' => 'Fallback',
            'lastname' => 'Name',
            'lang' => 'en',
            'isCompany' => false,
            'usergroup' => ',10,20,',
            'quiqqer.erp.taxId' => 'TAX-702',
            'address' => [
                'salutation' => 'mr',
                'firstname' => 'Alan',
                'lastname' => 'Turing',
                'country' => 'DE'
            ]
        ]);

        self::assertSame('Mr. Alan Turing', $User->getName());
        self::assertSame('DE', $User->getCountry()?->getCode());
        self::assertSame([$GroupA, $GroupB], $User->getGroups());
        self::assertSame('TAX-702', $User->getAttributes()['quiqqer.erp.taxId']);
        self::assertSame('alan', $User->getAttributes()['username']);
        self::assertSame('', $User->getCustomerNo());
        self::assertSame('', $User->getSupplierNo());
    }

    private function user(): User
    {
        return new User([
            'id' => 501,
            'uuid' => 'user-501',
            'country' => '',
            'username' => 'ada',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'lang' => 'en',
            'isCompany' => true,
            'customerId' => 'CUST-7',
            'supplierId' => 'SUP-9',
            'quiqqer.erp.isNettoUser' => 1,
            'quiqqer.erp.euVatId' => 'DE123',
            'usergroup' => ',10,20,',
            'address' => [
                'id' => 77,
                'uuid' => 'address-77',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                'company' => 'Analytical Engines Ltd.'
            ]
        ]);
    }
}
