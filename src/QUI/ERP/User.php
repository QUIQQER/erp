<?php

/**
 * This file contains QUI\ERP\User
 */

namespace QUI\ERP;

use QUI;
use QUI\Countries\Country;
use QUI\ERP\Customer\NumberRange as CustomerNumberRange;
use QUI\Groups\Group;
use QUI\Interfaces\Users\User as UserInterface;

use function array_filter;
use function array_flip;
use function array_walk;
use function explode;
use function get_class;
use function is_array;
use function is_bool;
use function json_decode;
use function trim;

/**
 * Class User
 * ERP User, a user object compatible to the QUIQQER User Interface
 *
 * @todo implement UUID
 * @package QUI\ERP
 */
class User extends QUI\QDOM implements UserInterface
{
    /**
     * @var int
     */
    protected int $id;

    /**
     * @var string|int
     */
    protected string|int $uuid;

    /**
     * @var string
     */
    protected string $username;

    /**
     * @var string
     */
    protected string $firstName;

    /**
     * @var string
     */
    protected string $lastName;

    /**
     * @var string
     */
    protected string $lang;

    /**
     * @var string
     */
    protected string $country;

    /**
     * @var bool
     */
    protected bool $isCompany;

    /**
     * @var bool|null
     */
    protected ?bool $isNetto = null;

    /**
     * Address data
     *
     * @var array
     */
    protected array $address = [];

    /**
     * User constructor.
     *
     * @param array $attributes
     * @throws QUI\ERP\Exception
     */
    public function __construct(array $attributes)
    {
        $needle = $this->getNeedles();

        foreach ($needle as $attribute) {
            if (!isset($attributes[$attribute])) {
                throw new QUI\ERP\Exception(
                    'Missing attribute:' . $attribute
                );
            }
        }

        $this->id = $attributes['id'];
        $this->isCompany = !empty($attributes['isCompany']) || !empty($attributes['company']);
        $this->lang = $attributes['lang'];
        $this->username = $attributes['username'];
        $this->firstName = $attributes['firstname'];
        $this->lastName = $attributes['lastname'];

        if ($attributes['country'] instanceof QUI\Countries\Country) {
            $this->country = $attributes['country']->getCode();
        } else {
            $this->country = $attributes['country'];
        }

        if (isset($attributes['uuid'])) {
            $this->uuid = $attributes['uuid'];
        }

        if (isset($attributes['data']) && is_array($attributes['data'])) {
            $this->setAttributes($attributes['data']);
            unset($attributes['data']);
        }

        if (isset($attributes['address']) && is_array($attributes['address'])) {
            $this->address = $attributes['address'];
        }

        $needle = array_flip($needle);

        foreach ($attributes as $attribute => $value) {
            if (!isset($needle[$attribute])) {
                $this->setAttribute($attribute, $value);
            }
        }

        $this->isNetto = $this->isNetto();
    }

    /**
     * Return the list of the needled attributes
     * @return array
     */
    public static function getNeedles(): array
    {
        return [
            'id',
            'country',
            'username',
            'firstname',
            'lastname',
            'lang',
            'isCompany'
        ];
    }

    /**
     * @param array $attributes - array('attribute' => 'value')
     * @return array
     */
    public static function getMissingAttributes(array $attributes): array
    {
        $missing = [];
        $needles = self::getNeedles();

        foreach ($needles as $needle) {
            if (!isset($attributes[$needle])) {
                $missing[] = $needle;
            }
        }

        return $missing;
    }

    /**
     * Convert a User to an ERP user
     *
     * @param UserInterface $User
     * @return self
     *
     * @throws QUI\ERP\Exception
     */
    public static function convertUserToErpUser(UserInterface $User): User
    {
        $Country = $User->getCountry();
        $country = '';

        if ($Country) {
            $country = $Country->getCode();
        }

        $address = false;

        if (!QUI::getUsers()->isNobodyUser($User) && !QUI::getUsers()->isSystemUser($User)) {
            /* @var $Address QUI\Users\Address */
            $Address = $User->getStandardAddress();

            if ($Address) {
                $address = $Address->getAttributes();
            }
        }

        $data = $User->getAttributes();
        unset($data['extra']);

        return new self([
            'id' => $User->getId(),
            'country' => $country,
            'username' => $User->getUsername(),
            'firstname' => $User->getAttribute('firstname'),
            'lastname' => $User->getAttribute('lastname'),
            'lang' => $User->getLang(),
            'isCompany' => $User->isCompany(),
            'isNetto' => $User->getAttribute('quiqqer.erp.isNettoUser'),
            'data' => $data,
            'address' => $address,
            'email' => $User->getAttribute('email'),

            'quiqqer.erp.euVatId' => $User->getAttribute('quiqqer.erp.euVatId'),
            'quiqqer.erp.taxId' => $User->getAttribute('quiqqer.erp.taxId')
        ]);
    }

    /**
     * Convert user data to an ERP user
     *
     * @param array $user {{$user.uid, $user.aid}}
     * @return self
     *
     * @throws QUI\ERP\Exception
     */
    public static function convertUserDataToErpUser(array $user): User
    {
        if (!isset($user['uid'])) {
            throw new QUI\ERP\Exception('Need uid param');
        }

        if (!isset($user['aid'])) {
            throw new QUI\ERP\Exception('Need aid param');
        }

        try {
            $User = QUI::getUsers()->get($user['uid']);
            $Address = $User->getAddress($user['aid']);
        } catch (QUI\Exception $Exception) {
            throw new QUI\ERP\Exception(
                $Exception->getMessage(),
                $Exception->getCode()
            );
        }

        $ERPUser = self::convertUserToErpUser($User);
        $ERPUser->setAddress($Address);

        return $ERPUser;
    }

    /**
     * @return int|false
     */
    public function getId(): int|false
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->getUUID();
    }

    /**
     * @return string
     */
    public function getUUID(): string
    {
        return $this->uuid;
    }

    /**
     * @return mixed
     */
    public function getName(): string
    {
        $Address = $this->getAddress();

        $salutation = $Address->getAttribute('salutation');
        $firstName = $Address->getAttribute('firstname');

        if (empty($firstName)) {
            $firstName = $this->firstName;
        }

        $lastName = $Address->getAttribute('lastname');

        if (empty($lastName)) {
            $lastName = $this->lastName;
        }

        $name = $firstName . ' ' . $lastName;

        if (!empty($salutation)) {
            switch ($salutation) {
                case 'Herr':
                case 'mr':
                    $salutation = QUI::getLocale()->get('quiqqer/quiqqer', 'address.salutation.male');
                    break;

                case 'Frau':
                case 'mrs':
                    $salutation = QUI::getLocale()->get('quiqqer/quiqqer', 'address.salutation.female');
                    break;
            }

            $name = $salutation . ' ' . $name;
        }

        return trim($name);
    }

    /**
     * Return the company if the customer has a company
     * if not, the user will be returned
     *
     * @return mixed
     */
    public function getInvoiceName(): string
    {
        if ($this->isCompany()) {
            $Address = $this->getAddress();
            $company = $Address->getAttribute('company');

            if (!empty($company)) {
                return $company;
            }
        }

        return $this->getName();
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @return QUI\Locale
     */
    public function getLocale(): QUI\Locale
    {
        $Locale = new QUI\Locale();
        $Locale->setCurrent($this->getLang());

        return $Locale;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        $attributes = parent::getAttributes();
        $attributes['country'] = $this->getCountry();
        $attributes['id'] = $this->getId();
        $attributes['lang'] = $this->getLang();
        $attributes['isCompany'] = $this->isCompany();
        $attributes['firstname'] = $this->getAttribute('firstname');
        $attributes['lastname'] = $this->getAttribute('lastname');
        $attributes['username'] = $this->getAttribute('username');
        $attributes['address'] = $this->getAddress()->getAttributes();

        if ($this->getAttribute('quiqqer.erp.euVatId')) {
            $attributes['quiqqer.erp.euVatId'] = $this->getAttribute('quiqqer.erp.euVatId');
        }

        if ($this->getAttribute('quiqqer.erp.taxId')) {
            $attributes['quiqqer.erp.taxId'] = $this->getAttribute('quiqqer.erp.taxId');
        }

        return $attributes;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getAttribute(string $name): mixed
    {
        switch ($name) {
            case 'firstname':
                return $this->firstName;

            case 'lastname':
                return $this->lastName;

            case 'country':
                return $this->getCountry();
        }

        return parent::getAttribute($name);
    }

    /**
     * @return mixed
     */
    public function getType(): string
    {
        return get_class($this);
    }

    /**
     * @return mixed
     */
    public function getStatus(): bool
    {
        return 0;
    }

    /**
     * Return the current address data
     *
     * @param int|string $id - only for the interface, has no effect
     * @return Address
     */
    public function getAddress(int|string $id = 0): QUI\Users\Address
    {
        return new Address($this->address, $this);
    }

    /**
     * @param QUI\Users\Address $Address
     */
    public function setAddress(QUI\Users\Address $Address): void
    {
        $this->address = json_decode($Address->toJSON(), true);
    }

    /**
     * @return Country|bool
     */
    public function getCountry(): QUI\Countries\Country|bool
    {
        if (!empty($this->address) && isset($this->address['country'])) {
            try {
                return QUI\Countries\Manager::get($this->address['country']);
            } catch (QUI\Exception) {
            }
        }

        if (!empty($this->country)) {
            try {
                return QUI\Countries\Manager::get($this->country);
            } catch (QUI\Exception) {
            }
        }

        return QUI\ERP\Defaults::getCountry();
    }

    /**
     * @return bool
     */
    public function isCompany(): bool
    {
        return $this->isCompany;
    }

    /**
     * @return bool
     */
    public function isNetto(): bool
    {
        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config = $Package->getConfig();

            if ($Config->getValue('general', 'businessType') === 'B2B') {
                return QUI\ERP\Utils\User::IS_NETTO_USER;
            }
        } catch (QUI\Exception) {
        }


        if ($this->existsAttribute('erp.isNettoUser')) {
            return (int)$this->getAttribute('erp.isNettoUser') === QUI\ERP\Utils\User::IS_NETTO_USER;
        }

        if ($this->existsAttribute('quiqqer.erp.isNettoUser')) {
            return (int)$this->getAttribute('quiqqer.erp.isNettoUser') === QUI\ERP\Utils\User::IS_NETTO_USER;
        }

        if ($this->isNetto === null) {
            $this->isNetto = QUI\ERP\Utils\User::getBruttoNettoUserStatus($this) === QUI\ERP\Utils\User::IS_NETTO_USER;
        }

        return $this->isNetto;
    }

    /**
     * @return bool
     */
    public function hasBruttoNettoStatus(): bool
    {
        return is_bool($this->isNetto);
    }

    /**
     * @return mixed
     */
    public function isSU(): bool
    {
        return false;
    }

    /**
     * @param int|string $groupId
     * @return mixed
     */
    public function isInGroup(int|string $groupId): bool
    {
        return false;
    }

    /**
     * @return mixed
     */
    public function canUseBackend(): bool
    {
        return false;
    }

    /**
     * Does nothing
     */
    public function logout()
    {
    }

    /**
     * @param string $code
     * @param User|UserInterface|null $PermissionUser
     * @return bool
     */
    public function activate(string $code, User|UserInterface|null $PermissionUser = null): bool
    {
        return true;
    }

    /**
     * @param UserInterface|null $PermissionUser
     * @return bool
     */
    public function deactivate(?UserInterface $PermissionUser = null): bool
    {
        return true;
    }

    /**
     * @param UserInterface|null $PermissionUser
     * @return true
     */
    public function disable(UserInterface|null $PermissionUser = null): bool
    {
        return true;
    }

    /**
     * Does nothing
     * @param UserInterface|null $PermissionUser
     */
    public function save(?UserInterface $PermissionUser = null)
    {
    }

    /**
     * @param UserInterface|null $PermissionUser
     * @return bool
     */
    public function delete(?UserInterface $PermissionUser = null): bool
    {
        return false;
    }

    /**
     * This user has nowhere permissions
     *
     * @param string $right
     * @param bool|array $ruleset
     * @return bool
     */
    public function getPermission(string $right, bool|array|string|callable $ruleset = false): bool
    {
        return false;
    }

    /**
     * @return Address
     */
    public function getStandardAddress(): Address
    {
        return $this->getAddress();
    }

    /**
     * Does nothing
     * @param array|string $groups
     */
    public function setGroups(array|string $groups)
    {
    }

    /**
     * @param bool $array
     * @return int[]|Group[]
     */
    public function getGroups(bool $array = true): array
    {
        $groupIds = $this->getAttribute('usergroup');

        if (empty($groupIds)) {
            return [];
        }

        if (!is_array($groupIds)) {
            $groupIds = explode(',', $groupIds);
        }

        $groupIds = array_filter($groupIds, function ($groupId) {
            return !empty($groupId);
        });

        array_walk($groupIds, function (&$groupId) {
            $groupId = (int)$groupId;
        });

        if (!$array) {
            return $groupIds;
        }

        $groups = [];

        foreach ($groupIds as $groupId) {
            try {
                $groups[] = QUI::getGroups()->get($groupId);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        return $groups;
    }

    /**
     * This user has no avatar, it returned the default placeholder image
     *
     * @return QUI\Projects\Media\Image|false
     *
     * @throws QUI\Exception
     */
    public function getAvatar(): QUI\Projects\Media\Image|bool
    {
        return QUI::getProjectManager()
            ->getStandard()
            ->getMedia()
            ->getPlaceholderImage();
    }

    /**
     * Does nothing
     * @param string $new
     * @param UserInterface|null $PermissionUser
     */
    public function setPassword(string $new, ?UserInterface $PermissionUser = null)
    {
    }

    /**
     * Does nothing
     * @param string $pass
     * @param bool $encrypted
     */
    public function checkPassword(string $pass, bool $encrypted = false)
    {
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isOnline(): bool
    {
        return false;
    }

    /**
     * Does nothing
     * @param bool $status
     */
    public function setCompanyStatus(bool $status)
    {
    }

    /**
     * Does nothing
     * @param int $groupId
     */
    public function addToGroup(int $groupId)
    {
    }

    /**
     * Does nothing
     * @param int|Group $Group
     */
    public function removeGroup(Group|int $Group)
    {
    }

    /**
     * Does nothing
     */
    public function refresh()
    {
    }

    // region Special ERP User API

    /**
     * Get customer no. of this ERP User.
     *
     * @return string
     */
    public function getCustomerNo(): string
    {
        $customerId = $this->getAttribute('customerId');

        if (empty($customerId)) {
            return '';
        }

        $NumberRange = new CustomerNumberRange();

        return $NumberRange->getCustomerNoPrefix() . $customerId;
    }

    /**
     * Get supplier no. of this ERP User.
     *
     * @return string
     */
    public function getSupplierNo(): string
    {
        $supplierNo = $this->getAttribute('supplierId');

        if (empty($supplierNo)) {
            return '';
        }

        return $supplierNo;
    }

    // endregion
}
