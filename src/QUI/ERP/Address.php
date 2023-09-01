<?php

/**
 * This file contains QUI\ERP\Address
 */

namespace QUI\ERP;

use QUI;
use QUI\ERP\Customer\Utils as CustomerUtils;

use function dirname;
use function is_numeric;

/**
 * Class Address
 *
 * @package QUI\ERP
 */
class Address extends QUI\Users\Address
{
    /**
     * Address constructor.
     *
     * @param array $data
     * @param User|null $User
     */
    public function __construct($data = [], $User = null)
    {
        $this->User = $User;
        $this->setAttributes($data);

        if (isset($data['id'])) {
            $this->id = (int)$data['id'];
        }
    }

    /**
     * Return the address as HTML display
     *
     * @param array $options - options ['mail' => true, 'tel' => true]
     * @return string - HTML <address>
     */
    public function getDisplay($options = []): string
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine(true);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return '';
        }

        $contactPerson = '';
        $isCompany = false;

        if ($this->User && $this->User->isCompany()) {
            $isCompany = $this->User->isCompany();
        }

        if (!empty($this->getAttribute('contactPerson'))) {
            $contactPerson = $this->getAttribute('contactPerson');
        } elseif ($this->User) {
            $ContactPersonAddress = CustomerUtils::getInstance()->getContactPersonAddress($this->User);

            if ($ContactPersonAddress) {
                $contactPerson = $ContactPersonAddress->getName();
            }
        }

        if ((bool)Defaults::conf('general', 'contactPersonOnAddress') === false) {
            $contactPerson = '';
        }

        if (is_numeric($contactPerson)) {
            $contactPerson = '';
        }

        $salutation = $this->emptyStringCheck($this->getAttribute('salutation'));
        $street_no = $this->emptyStringCheck($this->getAttribute('street_no'));
        $zip = $this->emptyStringCheck($this->getAttribute('zip'));
        $city = $this->emptyStringCheck($this->getAttribute('city'));
        $country = $this->emptyStringCheck($this->getAttribute('country'));
        $suffix = $this->emptyStringCheck($this->getAttribute('suffix'));

        $firstname = $this->getAttribute('firstname');
        $lastname = $this->getAttribute('lastname');

        if (empty($firstname) && $this->User) {
            $firstname = $this->User->getAttribute('firstname');
        }

        if (empty($lastname) && $this->User) {
            $lastname = $this->User->getAttribute('lastname');
        }


        $Engine->assign([
            'User' => $this->User,
            'Address' => $this,
            'Countries' => new QUI\Countries\Manager(),
            'options' => $options,

            'isCompany' => $isCompany,
            'salutation' => $salutation,
            'firstname' => $this->emptyStringCheck($firstname),
            'lastname' => $this->emptyStringCheck($lastname),
            'street_no' => $street_no,
            'zip' => $zip,
            'city' => $city,
            'country' => $country,
            'contactPerson' => $this->emptyStringCheck($contactPerson),
            'suffix' => $suffix
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Address.html');
    }

    public function save($PermissionUser = null)
    {
    }

    /**
     * @param $value
     * @return string
     */
    protected function emptyStringCheck($value): string
    {
        if (empty($value)) {
            return '';
        }

        return $value;
    }
}
