<?php

/**
 * This file contains QUI\ERP\Address
 */

namespace QUI\ERP;

use QUI;
use QUI\ERP\Customer\Utils as CustomerUtils;
use QUI\Interfaces\Users\User as QUIUserInterface;

use function dirname;
use function is_numeric;

/**
 * Class Address
 */
class Address extends QUI\Users\Address
{
    /**
     * Address constructor.
     *
     * @param array<mixed>|null $data
     * @param QUIUserInterface|null $User
     */
    public function __construct(?array $data = [], null | QUI\Interfaces\Users\User $User = null)
    {
        if ($User) {
            $this->User = $User;
        }

        if (!$data) {
            $data = [];
        }

        $this->setAttributes($data);

        if (isset($data['id'])) {
            $this->id = (int)$data['id'];
        }

        if (isset($data['uuid'])) {
            $this->uuid = $data['uuid'];
        }
    }

    /**
     * Return the address as HTML display
     *
     * @param array<mixed> $options - options ['mail' => true, 'tel' => true]
     * @return string - HTML <address>
     */
    public function getDisplay(array $options = []): string
    {
        $Engine = QUI::getTemplateManager()->getEngine(true);
        $User = isset($this->User) ? $this->User : null;

        $contactPerson = '';
        $isCompany = false;

        if ($User && $User->isCompany()) {
            $isCompany = $User->isCompany();
        } elseif (
            !empty($this->getAttribute('isCompany'))
            && !empty($this->getAttribute('company'))
        ) {
            $isCompany = true;
        }

        if (!empty($this->getAttribute('contactPerson'))) {
            $contactPerson = $this->getAttribute('contactPerson');
        } elseif ($User) {
            $ContactPersonAddress = CustomerUtils::getInstance()->getContactPersonAddress($User);

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

        if (empty($firstname) && $User) {
            $firstname = $User->getAttribute('firstname');
        }

        if (empty($lastname) && $User) {
            $lastname = $User->getAttribute('lastname');
        }


        $Engine->assign([
            'User' => $User,
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

    public function save(?QUIUserInterface $PermissionUser = null): void
    {
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function emptyStringCheck(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        return $value;
    }
}
