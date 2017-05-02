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
     * @param UserInterface|null $User - optional
     * @return Calc
     */
    public static function getInstance($User = null)
    {
        if (!$User && QUI::isBackend()) {
            $User = QUI::getUsers()->getSystemUser();
        }

        if (!QUI::getUsers()->isUser($User)
            && !QUI::getUsers()->isSystemUser($User)
        ) {
            $User = QUI::getUserBySession();
        }

        return new self($User);
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
     * Calculate a complete article list
     *
     * @param ArticleList $List
     * @param callable|boolean $callback - optional, callback function for the data array
     * @return ArticleList
     */
    public function calcArticleList(ArticleList $List, $callback = false)
    {
        // calc data
        if (!is_callable($callback)) {
            return $List->calc();
        }


        $articles    = $List->getArticles();
        $isNetto     = QUI\ERP\Utils\User::isNettoUser($this->getUser());
        $isEuVatUser = QUI\ERP\Tax\Utils::isUserEuVatUser($this->getUser());
        $Area        = QUI\ERP\Utils\User::getUserArea($this->getUser());

        $subSum   = 0;
        $nettoSum = 0;
        $vatArray = array();

        /* @var $Article Article */
        foreach ($articles as $Article) {
            // add netto price
            try {
                QUI::getEvents()->fireEvent(
                    'onQuiqqerErpCalcArticleListArticle',
                    array($this, $Article)
                );
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::write($Exception->getMessage(), QUI\System\Log::LEVEL_ERROR);
            }

            $this->calcArticlePrice($Article);

            $articleAttributes = $Article->toArray();

            $subSum   = $subSum + $articleAttributes['calculated_sum'];
            $nettoSum = $nettoSum + $articleAttributes['calculated_nettoSum'];

            $articleVatArray = $articleAttributes['calculated_vatArray'];
            $vat             = $articleAttributes['vat'];

            if (!isset($vatArray[$vat])) {
                $vatArray[$vat]        = $articleVatArray;
                $vatArray[$vat]['sum'] = 0;
            }

            $vatArray[$vat]['sum'] = $vatArray[$vat]['sum'] + $articleVatArray['sum'];
        }

        QUI\ERP\Debug::getInstance()->log('Berechnetet Artikelliste MwSt', 'quiqqer/erp');
        QUI\ERP\Debug::getInstance()->log($vatArray, 'quiqqer/erp');

        try {
            QUI::getEvents()->fireEvent(
                'onQuiqqerErpCalcArticleList',
                array($this, $List, $nettoSum)
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::write($Exception->getMessage(), QUI\System\Log::LEVEL_ERROR);
        }

        // @todo Preisfaktoren hier
        $nettoSubSum = $nettoSum;


        // vat text
        $vatLists  = array();
        $vatText   = array();
        $bruttoSum = $nettoSum;

        foreach ($vatArray as $vatEntry) {
            $vatLists[$vatEntry['vat']] = true; // liste für MWST texte

            $bruttoSum = $bruttoSum + $vatEntry['sum'];
        }

        foreach ($vatLists as $vat => $bool) {
            $vatText[$vat] = self::getVatText($vat, $this->getUser());
        }

        $callback(array(
            'sum'          => $bruttoSum,
            'subSum'       => $subSum,
            'nettoSum'     => $nettoSum,
            'nettoSubSum'  => $nettoSubSum,
            'vatArray'     => $vatArray,
            'vatText'      => $vatText,
            'isEuVat'      => $isEuVatUser,
            'isNetto'      => $isNetto,
            'currencyData' => $this->getCurrency()->toArray()
        ));

        return $List;
    }

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
        $nettoSubSum     = $this->round($nettoPrice * $Article->getQuantity());

        // discounts
        $Discount = $Article->getDiscount();

        if ($Discount) {
            switch ($Discount->getCalculation()) {
                // einfache Zahl, Währung --- kein Prozent
                case Calc::CALCULATION_COMPLEMENT:
                    $nettoPrice = $nettoPrice + ($Discount->getValue() / $Article->getQuantity());
//                    $Discount->setNettoSum($this, $Discount->getValue());
                    break;

                // Prozent Angabe
                case Calc::CALCULATION_PERCENTAGE:
                    $percentage = $Discount->getValue() / 100 * $nettoPrice;
                    $nettoPrice = $nettoPrice + $percentage;
//                    $Discount->setNettoSum($this, $percentage);
                    break;
            }
        }

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

        $data = array(
            'basisPrice' => $basisPrice,
            'price'      => $price,
            'sum'        => $sum,

            'nettoBasisPrice' => $basisNettoPrice,
            'nettoPrice'      => $nettoPrice,
            'nettoSubSum'     => $nettoSubSum,
            'nettoSum'        => $nettoSum,

            'currencyData' => $this->getCurrency()->toArray(),
            'vatArray'     => $vatArray,
            'vatText'      => $vatArray['text'],
            'isEuVat'      => $isEuVatUser,
            'isNetto'      => $isNetto
        );

        QUI\ERP\Debug::getInstance()->log($data, 'quiqqer/erp');

        $callback($data);

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
