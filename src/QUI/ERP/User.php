<?php

/**
 * This file contains QUI\ERP\User
 */

namespace QUI\ERP;

use QUI;
use QUI\Interfaces\Users\User as UserInterface;

/**
 * Class User
 * ERP User, an user object compatible to the QUIQQER User Interface
 *
 * @package QUI\ERP
 */
class User extends QUI\QDOM implements UserInterface
{
    /**
     * @var int
     */
    protected $id;

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
     * @var array
     */
    protected $data = array();

    /**
     * Address data
     *
     * @var array
     */
    protected $address = array();

    /**
     * User constructor.
     *
     * @param array $attributes
     * @throws QUI\ERP\Exception
     */
    public function __construct(array $attributes)
    {
        $needle = array(
            'id',
            'country',
            'username',
            'firstname',
            'lastname',
            'lang',
            'isCompany'
        );

        foreach ($needle as $attribute) {
            if (!isset($attributes[$attribute])) {
                throw new QUI\ERP\Exception(
                    'Missing attribute:' . $attribute
                );
            }
        }

        $this->id        = $attributes['id'];
        $this->isCompany = (bool)$attributes['isCompany'];

        $this->lang      = $attributes['lang'];
        $this->username  = $attributes['username'];
        $this->firstName = $attributes['firstname'];
        $this->lastName  = $attributes['lastname'];
        $this->country   = $attributes['country'];

        if (isset($attributes['data']) && is_array($attributes['data'])) {
            $this->data = $attributes['data'];
            $this->setAttributes($this->data);
        }

        if (isset($attributes['address']) && is_array($attributes['address'])) {
            $this->address = $attributes['address'];
        }
    }

    /**
     * Convert a User to an ERP user
     *
     * @param QUI\Users\User $User
     * @return self
     */
    public static function convertUserToErpUser(QUI\Users\User $User)
    {
        $Country = $User->getCountry();
        $country = '';

        if ($Country) {
            $country = $Country->getCode();
        }

        return new self(array(
            'id'        => $User->getId(),
            'country'   => $country,
            'username'  => $User->getUsername(),
            'firstname' => $User->getAttribute('firstname'),
            'lastname'  => $User->getAttribute('lastname'),
            'lang'      => $User->getLang(),
            'isCompany' => $User->isCompany(),
            'data'      => $User->getAttributes()
        ));
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
            $User    = QUI::getUsers()->get($user['uid']);
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
    public function getName()
    {
        return $this->firstName . ' ' . $this->lastName;
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
     * @return mixed
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

        $attributes['country']   = $this->getCountry();
        $attributes['lang']      = $this->getLang();
        $attributes['isCompany'] = $this->isCompany();

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
     * @return mixed
     * @throws QUI\Exception
     */
    public function getCountry()
    {
        return QUI\Countries\Manager::get($this->country);
    }

    /**
     * @return bool
     */
    public function isCompany()
    {
        return $this->isCompany;
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
     * @param bool|\QUI\Users\User $ParentUser
     * @return mixed
     */
    public function disable($ParentUser = false)
    {
        return true;
    }

    /**
     * Does nothing
     * @param bool|\QUI\Users\User $ParentUser
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
     * @param bool $array
     * @return array
     */
    public function getGroups($array = true)
    {
        return array();
    }

    /**
     * This user has no avatar, it returned the default placeholder image
     *
     * @return QUI\Projects\Media\Image|false
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
     * @param bool|\QUI\Users\User $ParentUser
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
     * @param int|\QUI\Groups\Group $Group
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
}
