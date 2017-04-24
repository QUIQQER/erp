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
 * @info Produkt Berechnungen sind zu finden unter: QUI\ERP\Products\Utils\Calc
 *
 * @package QUI\ERP\Accounting
 */
class Calc
{
    /**
     * Percentage calculation
     */
    const CALCULATION_PERCENTAGE = 1; // @todo raus und in product calc lassen

    /**
     * Standard calculation
     */
    const CALCULATION_COMPLEMENT = 2; // @todo raus und in product calc lassen

    /**
     * Basis calculation -> netto
     */
    const CALCULATION_BASIS_NETTO = 1; // @todo raus und in product calc lassen

    /**
     * Basis calculation -> from current price
     */
    const CALCULATION_BASIS_CURRENTPRICE = 2; // @todo raus und in product calc lassen

    /**
     * Basis brutto
     * include all price factors (from netto calculated price)
     * warning: its not brutto VAT
     *
     * geht vnn der netto basis aus, welche alle price faktoren schon beinhaltet
     * alle felder sind in diesem price schon enthalten
     */
    const CALCULATION_BASIS_BRUTTO = 3; // @todo raus und in product calc lassen

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

//    /**
//     * @param $Product
//     * @return array
//     */
//    public function getProductPrice($Product)
//    {
//        if (class_exists('QUI\ERP\Products\Product\Product')
//            && $Product instanceof QUI\ERP\Products\Product\Product
//        ) {
//            $Calc  = QUI\ERP\Products\Utils\Calc::getInstance($this->getUser());
//            $Price = $Calc->getProductPrice($Product->createUniqueProduct());
//
//            return $Price->toArray();
//        }
//
//        if (class_exists('QUI\ERP\Products\Product\UniqueProduct')
//            && $Product instanceof QUI\ERP\Products\Product\UniqueProduct
//        ) {
//            $Calc  = QUI\ERP\Products\Utils\Calc::getInstance($this->getUser());
//            $Price = $Calc->getProductPrice($Product);
//
//            return $Price->toArray();
//        }
//
//        if ($Product instanceof InvoiceProduct) {
//            return self::calcArticlePrice($Product)->toArray();
//        }
//
//    }

    /**
     * Calculate the price of an article
     *
     * @param Article $Article
     * @param bool|callable $callback
     * @return mixed
     */
    public function calcArticlePrice(Article $Article, $callback = false)
    {
        // calc data
        if (!is_callable($callback)) {
            $Article->calc($this);

            return $Article->getPrice();
        }

        $isNetto     = QUI\ERP\Utils\User::isNettoUser($this->getUser());
        $isEuVatUser = QUI\ERP\Tax\Utils::isUserEuVatUser($this->getUser());

        $nettoPrice      = $Article->getUnitPrice();
        $vat             = $Article->getVat();
        $basisNettoPrice = $nettoPrice;

        $vatSum      = $nettoPrice * ($vat / 100);
        $bruttoPrice = $this->round($nettoPrice + $vatSum);

        // sum
        $nettoSum  = $this->round($nettoPrice * $Article->getQuantity());
        $vatSum    = $nettoSum * ($vat / 100);
        $bruttoSum = $this->round($nettoSum + $vatSum);

        $price      = $isNetto ? $nettoPrice : $bruttoPrice;
        $sum        = $isNetto ? $nettoSum : $bruttoSum;
        $basisPrice = $isNetto ? $basisNettoPrice : $basisNettoPrice + ($basisNettoPrice * $vat / 100);

        $vatArray = array(
            'vat'  => $vat,
            'sum'  => $this->round($nettoSum * ($vat / 100)),
            'text' => $this->getVatText($vat, $this->getUser())
        );


        QUI\ERP\Debug::getInstance()->log(
            'Kalkulierter Artikel Preis ' . $Article->getId(),
            'quiqqer/erp'
        );

        QUI\ERP\Debug::getInstance()->log(array(
            'basisPrice'   => $basisPrice,
            'price'        => $price,
            'sum'          => $sum,
            'nettoSum'     => $nettoSum,
            'vatArray'     => $vatArray,
            'isEuVat'      => $isEuVatUser,
            'isNetto'      => $isNetto,
            'currencyData' => $this->getCurrency()->toArray()
        ), 'quiqqer/erp');


        $callback(array(
            'basisPrice'   => $basisPrice,
            'price'        => $price,
            'sum'          => $sum,
            'nettoSum'     => $nettoSum,
            'vatArray'     => $vatArray,
            'vatText'      => $vatArray['text'],
            'isEuVat'      => $isEuVatUser,
            'isNetto'      => $isNetto,
            'currencyData' => $this->getCurrency()->toArray()
        ));

        return $Article->getPrice();
    }

    /**
     * Rounds the value via shop config
     *
     * @param string $value
     * @return float|mixed
     */
    public function round($value)
    {
        $decimalSeparator  = $this->getUser()->getLocale()->getDecimalSeparator();
        $groupingSeparator = $this->getUser()->getLocale()->getGroupingSeparator();
        $precision         = 8; // nachkommstelle beim runden -> @todo in die conf?

        if (strpos($value, $decimalSeparator) && $decimalSeparator != ' . ') {
            $value = str_replace($groupingSeparator, '', $value);
        }

        $value = str_replace(',', ' . ', $value);
        $value = round($value, $precision);

        return $value;
    }


    /**
     * Return the tax message for an user
     *
     * @return string
     */
    public function getVatTextByUser()
    {
        $Tax = QUI\ERP\Tax\Utils::getTaxByUser($this->getUser());

        return $this->getVatText($Tax->getValue(), $this->getUser());
    }

    /**
     * Return tax text
     * eq: incl or zzgl
     *
     * @param integer $vat
     * @param UserInterface $User
     * @return array|string
     */
    public static function getVatText($vat, UserInterface $User)
    {
        $Locale = $User->getLocale();

        if (QUI\ERP\Utils\User::isNettoUser($User)) {
            if (QUI\ERP\Tax\Utils::isUserEuVatUser($User)) {
                return $Locale->get(
                    'quiqqer/tax',
                    'message.vat.text.netto.EUVAT',
                    array('vat' => $vat)
                );
            }

            // vat ist leer und kein EU vat user
            if (!$vat) {
                return '';
            }

            return $Locale->get(
                'quiqqer/tax',
                'message.vat.text.netto',
                array('vat' => $vat)
            );
        }

        if (QUI\ERP\Tax\Utils::isUserEuVatUser($User)) {
            return $Locale->get(
                'quiqqer/tax',
                'message.vat.text.brutto.EUVAT',
                array('vat' => $vat)
            );
        }

        // vat ist leer und kein EU vat user
        if (!$vat) {
            return '';
        }

        return $Locale->get(
            'quiqqer/tax',
            'message.vat.text.brutto',
            array('vat' => $vat)
        );
    }
}
