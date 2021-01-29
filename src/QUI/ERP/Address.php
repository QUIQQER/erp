<?php

/**
 * This file contains QUI\ERP\Address
 */

namespace QUI\ERP;

use QUI;

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

        $isCompany = false;

        if ($this->User->isCompany()) {
            $isCompany = $this->User->isCompany();
        }

        $salutation = $this->emptyStringCheck($this->getAttribute('salutation'));
        $street_no  = $this->emptyStringCheck($this->getAttribute('street_no'));
        $zip        = $this->emptyStringCheck($this->getAttribute('zip'));
        $city       = $this->emptyStringCheck($this->getAttribute('city'));
        $country    = $this->emptyStringCheck($this->getAttribute('country'));

        $firstname = $this->getAttribute('firstname');
        $lastname  = $this->getAttribute('lastname');

        if (empty($firstname) && $this->User) {
            $firstname = $this->User->getAttribute('firstname');
        }

        if (empty($lastname) && $this->User) {
            $lastname = $this->User->getAttribute('lastname');
        }


        $Engine->assign([
            'User'      => $this->User,
            'Address'   => $this,
            'Countries' => new QUI\Countries\Manager(),
            'options'   => $options,

            'isCompany'  => $isCompany,
            'salutation' => $salutation,
            'firstname'  => $this->emptyStringCheck($firstname),
            'lastname'   => $this->emptyStringCheck($lastname),
            'street_no'  => $street_no,
            'zip'        => $zip,
            'city'       => $city,
            'country'    => $country,
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Address.html');
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
