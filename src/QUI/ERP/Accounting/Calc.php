<?php

/**
 * This file contains QUI\ERP\Accounting\Calc
 */
namespace QUI\ERP\Accounting;

use QUI;
use QUI\Interfaces\Users\User as UserInterface;

/**
 * Class Calc
 * Calculations for Accounting
 *
 * @package QUI\ERP\Accounting
 */
class Calc
{
    /**
     * @var UserInterface
     */
    protected $User = null;

    /**
     * @var null|QUI\ERP\Currency\Currency
     */
    protected $Currency = null;

    /**
     * Flag for ignore vat calculation (force ignore VAT)
     *
     * @var bool
     */
    protected $ignoreVatCalculation = false;

    /**
     * Calc constructor.
     *
     * @param UserInterface|bool $User - calculation user
     */
    public function __construct($User = false)
    {
        if (!QUI::getUsers()->isUser($User)) {
            $User = QUI::getUserBySession();
        }

        $this->User = $User;
    }

    /**
     * Static instance create
     *
     * @param UserInterface|bool $User - optional
     * @return Calc
     */
    public static function getInstance($User = false)
    {
        if (!$User && QUI::isBackend()) {
            $User = QUI::getUsers()->getSystemUser();
        }

        if (!QUI::getUsers()->isUser($User)
            && !QUI::getUsers()->isSystemUser($User)
        ) {
            $User = QUI::getUserBySession();
        }

        $Calc = new self($User);

        if (QUI::getUsers()->isSystemUser($User) && QUI::isBackend()) {
            $Calc->ignoreVatCalculation();
        }

        return $Calc;
    }

    /**
     * Static instance create
     */
    public function ignoreVatCalculation()
    {
        $this->ignoreVatCalculation = true;
    }

    /**
     * Set the calculation user
     * All calculations are made in dependence from this user
     *
     * @param UserInterface $User
     */
    public function setUser(UserInterface $User)
    {
        $this->User = $User;
    }

    /**
     * Return the calc user
     *
     * @return UserInterface
     */
    public function getUser()
    {
        return $this->User;
    }

    /**
     * Return the currency
     *
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        if (is_null($this->Currency)) {
            $this->Currency = QUI\ERP\Currency\Handler::getDefaultCurrency();
        }

        return $this->Currency;
    }

    /**
     * @param $Product
     * @return array
     */
    public function getProductPrice($Product)
    {
        if (class_exists('QUI\ERP\Products\Product\Product')
            && $Product instanceof QUI\ERP\Products\Product\Product
        ) {
            $Calc  = QUI\ERP\Products\Utils\Calc::getInstance($this->getUser());
            $Price = $Calc->getProductPrice($Product->createUniqueProduct());

            return $Price->toArray();
        }

        if (class_exists('QUI\ERP\Products\Product\UniqueProduct')
            && $Product instanceof QUI\ERP\Products\Product\UniqueProduct
        ) {
            $Calc  = QUI\ERP\Products\Utils\Calc::getInstance($this->getUser());
            $Price = $Calc->getProductPrice($Product);

            return $Price->toArray();
        }

        return array();
    }
}
