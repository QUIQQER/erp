<?php

/**
 * This file contains QUI\ERP\User
 */

namespace QUI\ERP;

use QUI;
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
 * ERP User, an user object compatible to the QUIQQER User Interface
 *
 * @todo implement UUID
 * @package QUI\ERP
 */
class User extends QUI\QDOM implements UserInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var mixed
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $firstName;

    /**
     * @var string
     */
    protected $lastName;

    /**
     * @var string
     */
    protected $lang;

    /**
     * @var string
     */
    protected $country;

    /**
     * @var bool
     */
    protected $isCompany;

    /**
     * @var bool|null
     */
    protected $isNetto;

    /**
     * Address data
     *
     * @var array
     */
    protected $address = [];

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
        $this->isNetto = null;

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
    }

    /**
     * Return the list of the needled attributes
     * @return array
     */
    public static function getNeedles()
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
    public static function getMissingAttributes(array $attributes)
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
     * @param QUI\Interfaces\Users\User $User
     * @return self
     *
     * @throws QUI\ERP\Exception
     */
    public static function convertUserToErpUser(QUI\Interfaces\Users\User $User)
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
            $address = $Address->getAttributes();
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
    public static function convertUserDataToErpUser($user)
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
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUniqueId()
    {
        return $this->uuid;
    }

    /**
     * @return mixed
     */
    public function getName()
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
    public function getInvoiceName()
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
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @return QUI\Locale
     */
    public function getLocale()
    {
        $Locale = new QUI\Locale();
        $Locale->setCurrent($this->getLang());

        return $Locale;
    }

    /**
     * @return array
     */
    public function getAttributes()
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
     * @param string $attribute
     * @return string
     */
    public function getAttribute($attribute)
    {
        switch ($attribute) {
            case 'firstname':
                return $this->firstName;

            case 'lastname':
                return $this->lastName;

            case 'country':
                return $this->getCountry();
        }

        return parent::getAttribute($attribute);
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return get_class($this);
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return 0;
    }

    /**
     * Return the current address data
     *
     * @param int $id - only for the interface, has no effect
     * @return Address
     */
    public function getAddress($id = 0)
    {
        return new Address($this->address, $this);
    }

    /**
     * @param QUI\Users\Address $Address
     */
    public function setAddress(QUI\Users\Address $Address)
    {
        $this->address = json_decode($Address->toJSON(), true);
    }

    /**
     * @return QUI\Countries\Country
     */
    public function getCountry()
    {
        if (!empty($this->address) && isset($this->address['country'])) {
            try {
                return QUI\Countries\Manager::get($this->address['country']);
            } catch (QUI\Exception $Exception) {
            }
        }

        if (!empty($this->country)) {
            try {
                return QUI\Countries\Manager::get($this->country);
            } catch (QUI\Exception $Exception) {
            }
        }

        return QUI\ERP\Defaults::getCountry();
    }

    /**
     * @return bool
     */
    public function isCompany()
    {
        return $this->isCompany;
    }

    /**
     * @return bool
     */
    public function isNetto()
    {
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
    public function hasBruttoNettoStatus()
    {
        return is_bool($this->isNetto);
    }

    /**
     * @return mixed
     */
    public function isSU()
    {
        return false;
    }

    /**
     * @param int $groupId
     * @return mixed
     */
    public function isInGroup($groupId)
    {
        return false;
    }

    /**
     * @return mixed
     */
    public function canUseBackend()
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
     * @return mixed
     */
    public function activate($code)
    {
        return true;
    }

    /**
     * @return mixed
     */
    public function deactivate()
    {
        return true;
    }

    /**
     * @param bool|QUI\Users\User $ParentUser
     * @return mixed
     */
    public function disable($ParentUser = false)
    {
        return true;
    }

    /**
     * Does nothing
     * @param bool|QUI\Users\User $ParentUser
     */
    public function save($ParentUser = false)
    {
    }

    /**
     * @return mixed
     */
    public function delete()
    {
        return false;
    }

    /**
     * This user has nowhere permissions
     *
     * @param string $right
     * @param array|bool $ruleset
     * @return bool
     */
    public function getPermission($right, $ruleset = false)
    {
        return false;
    }

    /**
     * @return Address
     */
    public function getStandardAddress()
    {
        return $this->getAddress();
    }

    /**
     * Does nothing
     * @param array|string $groups
     */
    public function setGroups($groups)
    {
    }

    /**
     * @param bool $asObjects
     * @return int[]|Group[]
     */
    public function getGroups($asObjects = true)
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

        if (!$asObjects) {
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
    public function getAvatar()
    {
        return QUI::getProjectManager()
            ->getStandard()
            ->getMedia()
            ->getPlaceholderImage();
    }

    /**
     * Does nothing
     * @param string $new
     * @param bool|QUI\Users\User $ParentUser
     */
    public function setPassword($new, $ParentUser = false)
    {
    }

    /**
     * Does nothing
     * @param string $pass
     * @param bool $encrypted
     */
    public function checkPassword($pass, $encrypted = false)
    {
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isOnline()
    {
        return false;
    }

    /**
     * Does nothing
     * @param bool $status
     */
    public function setCompanyStatus($status)
    {
    }

    /**
     * Does nothing
     * @param int $groupId
     */
    public function addToGroup($groupId)
    {
    }

    /**
     * Does nothing
     * @param int|Group $Group
     */
    public function removeGroup($Group)
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
    public function getSupplierNo()
    {
        $supplierNo = $this->getAttribute('supplierId');

        if (empty($supplierNo)) {
            return '';
        }

        return $supplierNo;
    }

    // endregion
}
