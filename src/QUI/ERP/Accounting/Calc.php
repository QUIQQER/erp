<?php

/**
 * This file contains QUI\ERP\Accounting\Calc
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Money\Price;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Handler;

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
//        $Area        = QUI\ERP\Utils\User::getUserArea($this->getUser());

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
            $calculated        = $articleAttributes['calculated'];

            $subSum   = $subSum + $calculated['sum'];
            $nettoSum = $nettoSum + $calculated['nettoSum'];

            $articleVatArray = $calculated['vatArray'];
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
        // nur wenn wir welche benötigen, für ERP Artikel ist dies im Moment nicht wirklich nötig
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
                    break;

                // Prozent Angabe
                case Calc::CALCULATION_PERCENTAGE:
                    $percentage = $Discount->getValue() / 100 * $nettoPrice;
                    $nettoPrice = $nettoPrice + $percentage;
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

    /**
     * Calculates the individual amounts paid of an invoice
     *
     * @param Invoice $Invoice
     * @return array
     */
    public static function calculateInvoicePayments(Invoice $Invoice)
    {
        $paidData = $Invoice->getAttribute('paid_data');

        if (!is_array($paidData)) {
            $paidData = json_decode($paidData, true);
        }

        if (!is_array($paidData)) {
            $paidData = array();
        }

        $payments = array();
        $paidDate = 0;
        $sum      = 0;
        $total    = $Invoice->getAttribute('sum');

        $isValidTimeStamp = function ($timestamp) {
            return ((string)(int)$timestamp === $timestamp)
                   && ($timestamp <= PHP_INT_MAX)
                   && ($timestamp >= ~PHP_INT_MAX);
        };

        foreach ($paidData as $data) {
            if (!isset($data['date']) ||
                !isset($data['amount'])
            ) {
                continue;
            }

            // calculate the paid amount
            $amount = Price::validatePrice($data['amount']);

            // set the newest date
            $date = $data['date'];

            if ($isValidTimeStamp($date) === false) {
                $date = strtotime($date);

                if ($isValidTimeStamp($date) === false) {
                    $date = time();
                }
            } else {
                $date = (int)$date;
            }

            if ($date > $paidDate) {
                $paidDate = $date;
            }


            // Falls das gezahlte mehr ist
            if ($total < ($sum + $amount)) {
                $amount = $total - $sum;

                // @todo Information in Rechnung hinterlegen
                // @todo Automatische Gutschrift erstellen
            }

            $sum = $sum + $amount;

            $payments[] = array(
                'amount'  => $amount,
                'date'    => $date,
                'payment' => $data['payment']
            );
        }

        $Invoice->setAttribute('paid_data', json_encode($paidData));
        $Invoice->setAttribute('paid_date', $paidDate);
        $Invoice->setAttribute('paid', $sum);
        $Invoice->setAttribute('toPay', $Invoice->getAttribute('sum') - $sum);


        if ($Invoice->getAttribute('paid_status') === Handler::TYPE_INVOICE_REVERSAL
            || $Invoice->getAttribute('paid_status') === Handler::TYPE_INVOICE_CANCEL
        ) {
            // Leave everything as it is
        } elseif ((float)$Invoice->getAttribute('toPay') == 0) {
            $Invoice->setAttribute('paid_status', Invoice::PAYMENT_STATUS_PAID);
        } elseif ($Invoice->getAttribute('paid') == 0) {
            $Invoice->setAttribute('paid_status', Invoice::PAYMENT_STATUS_OPEN);
        } elseif ($Invoice->getAttribute('toPay')
                  && $Invoice->getAttribute('sum') != $Invoice->getAttribute('paid')
        ) {
            $Invoice->setAttribute('paid_status', Invoice::PAYMENT_STATUS_PART);
        }

        return array(
            'paidData' => $paidData,
            'paidDate' => $Invoice->getAttribute('paid_date'),
            'paid'     => $Invoice->getAttribute('paid'),
            'toPay'    => $Invoice->getAttribute('toPay')
        );
    }

    /**
     * Calculate the total of the invoice list
     *
     * @param array $invoiceList - list of invoice array
     * @return array
     */
    public static function calculateTotal(array $invoiceList)
    {
        if (!count($invoiceList)) {
            $Currency = QUI\ERP\Defaults::getCurrency();
            $display  = $Currency->format(0);

            return array(
                'netto_toPay'         => 0,
                'netto_paid'          => 0,
                'netto_total'         => 0,
                'display_netto_toPay' => $display,
                'display_netto_paid'  => $display,
                'display_netto_total' => $display,

                'vat_toPay'         => 0,
                'vat_paid'          => 0,
                'vat_total'         => 0,
                'display_vat_toPay' => $display,
                'display_vat_paid'  => $display,
                'display_vat_total' => $display,

                'brutto_toPay'         => 0,
                'brutto_paid'          => 0,
                'brutto_total'         => 0,
                'display_brutto_toPay' => $display,
                'display_brutto_paid'  => $display,
                'display_brutto_total' => $display
            );
        }

        try {
            $currency = json_decode($invoiceList[0]['currency_data'], true);
            $Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);
        } catch (QUI\Exception $Exception) {
            $Currency = QUI\ERP\Defaults::getCurrency();
        }

        $nettoTotal = 0;
        $vatTotal   = 0;

        $bruttoToPay = 0;
        $bruttoPaid  = 0;
        $bruttoTotal = 0;

        foreach ($invoiceList as $invoice) {
            $nettoTotal = $nettoTotal + $invoice['calculated_nettosum'];
            $vatTotal   = $vatTotal + $invoice['calculated_vatsum'];

            $bruttoTotal = $bruttoTotal + $invoice['calculated_sum'];
            $bruttoPaid  = $bruttoPaid + $invoice['calculated_paid'];
            $bruttoToPay = $bruttoToPay + $invoice['calculated_toPay'];
        }

        $openPercent = QUI\Utils\Math::percent($bruttoToPay, $bruttoTotal);
        $paidPercent = QUI\Utils\Math::percent($bruttoPaid, $bruttoTotal);

        // netto calculation
        $nettoToPay = $nettoTotal * $openPercent / 100;
        $nettoPaid  = $nettoTotal * $paidPercent / 100;

        // vat calculation
        $vatToPay = $bruttoToPay - $nettoToPay;
        $vatPaid  = $bruttoPaid - $nettoPaid;

        return array(
            'netto_toPay'         => $nettoToPay,
            'netto_paid'          => $nettoPaid,
            'netto_total'         => $nettoTotal,
            'display_netto_toPay' => $Currency->format($nettoToPay),
            'display_netto_paid'  => $Currency->format($nettoPaid),
            'display_netto_total' => $Currency->format($nettoTotal),

            'vat_toPay'         => $vatToPay,
            'vat_paid'          => $vatPaid,
            'vat_total'         => $vatTotal,
            'display_vat_toPay' => $Currency->format($vatToPay),
            'display_vat_paid'  => $Currency->format($vatPaid),
            'display_vat_total' => $Currency->format($vatTotal),

            'brutto_toPay'         => $bruttoToPay,
            'brutto_paid'          => $bruttoPaid,
            'brutto_total'         => $bruttoTotal,
            'display_brutto_toPay' => $Currency->format($bruttoToPay),
            'display_brutto_paid'  => $Currency->format($bruttoPaid),
            'display_brutto_total' => $Currency->format($bruttoTotal)
        );
    }

    /**
     * Return the total of all vats
     *
     * @param string|array $vatArray
     * @return float|int
     */
    public static function calculateTotalVatOfInvoice($vatArray)
    {
        if (is_string($vatArray)) {
            $vatArray = json_decode($vatArray, true);
        }

        return array_sum(
            array_map(function ($vat) {
                return $vat['sum'];
            }, $vatArray)
        );
    }
}
