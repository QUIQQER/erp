<?php

/**
 * This file contains QUI\ERP\Accounting\Calc
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Money\Price;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
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
    const CALCULATION_PERCENTAGE = 1;

    /**
     * Standard calculation
     */
    const CALCULATION_COMPLEMENT = 2;

    /**
     * Set the price for the product
     */
    const CALCULATION_COMPLETE = 3;

    /**
     * Basis calculation -> netto
     */
    const CALCULATION_BASIS_NETTO = 1;

    /**
     * Basis calculation -> from current price
     */
    const CALCULATION_BASIS_CURRENTPRICE = 2;

    /**
     * Basis brutto
     * include all price factors (from netto calculated price)
     * warning: its not brutto VAT
     *
     * geht vnn der netto basis aus, welche alle price faktoren schon beinhaltet
     * alle felder sind in diesem price schon enthalten
     */
    const CALCULATION_BASIS_BRUTTO = 3;

    /**
     * Berechnet auf Basis des Preises inklusive Steuern
     * Zum Beispiel MwSt
     *
     */
    const CALCULATION_BASIS_VAT_BRUTTO = 4;

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

        if (!QUI::getUsers()->isUser($User) && !QUI::getUsers()->isSystemUser($User)) {
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
        if (\is_null($this->Currency)) {
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
        if (!\is_callable($callback)) {
            return $List->calc();
        }

        // user order address
        $Order = $List->getOrder();

        if ($Order) {
            $this->getUser()->setAttribute('CurrentAddress', $Order->getDeliveryAddress());
        }

        $this->Currency = $List->getCurrency();

        $articles    = $List->getArticles();
        $isNetto     = QUI\ERP\Utils\User::isNettoUser($this->getUser());
        $isEuVatUser = QUI\ERP\Tax\Utils::isUserEuVatUser($this->getUser());

        $Currency  = $this->getCurrency();
        $precision = $Currency->getPrecision();

        $subSum   = 0;
        $nettoSum = 0;
        $vatArray = [];

        foreach ($articles as $Article) {
            // add netto price
            try {
                QUI::getEvents()->fireEvent(
                    'onQuiqqerErpCalcArticleListArticle',
                    [$this, $Article]
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

            if ($articleVatArray['text'] === '') {
                continue;
            }

            if (!isset($vatArray[$vat])) {
                $vatArray[$vat]        = $articleVatArray;
                $vatArray[$vat]['sum'] = 0;
            }

            $vatArray[$vat]['sum'] = $vatArray[$vat]['sum'] + $articleVatArray['sum'];
        }

        QUI\ERP\Debug::getInstance()->log('Berechnete Artikelliste MwSt', 'quiqqer/erp');
        QUI\ERP\Debug::getInstance()->log($vatArray, 'quiqqer/erp');

        try {
            QUI::getEvents()->fireEvent(
                'onQuiqqerErpCalcArticleList',
                [$this, $List, $nettoSum]
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::write($Exception->getMessage(), QUI\System\Log::LEVEL_ERROR);
        }

        /**
         * Calc price factors
         */
        $priceFactors   = $List->getPriceFactors();
        $priceFactorSum = 0;

        // nur wenn wir welche benötigen, für ERP Artikel ist dies im Moment nicht wirklich nötig
        $nettoSubSum = $nettoSum;

        /* @var $PriceFactor QUI\ERP\Accounting\PriceFactors\Factor */
        foreach ($priceFactors as $PriceFactor) {
            // percent - Prozent Angabe
            if ($PriceFactor->getCalculation() === self::CALCULATION_PERCENTAGE) {
                $calcBasis        = $PriceFactor->getCalculationBasis();
                $priceFactorValue = $PriceFactor->getValue();
                $vatValue         = $PriceFactor->getVat();

                if ($vatValue === null) {
                    $vatValue = QUI\ERP\Tax\Utils::getTaxByUser($this->getUser())->getValue();
                }

                switch ($calcBasis) {
                    default:
                    case self::CALCULATION_BASIS_NETTO:
                        $percentage = $priceFactorValue / 100 * $nettoSubSum;
                        break;

                    case self::CALCULATION_BASIS_BRUTTO:
                    case self::CALCULATION_BASIS_CURRENTPRICE:
                        $percentage = $priceFactorValue / 100 * $nettoSum;
                        break;

                    case self::CALCULATION_BASIS_VAT_BRUTTO:
                        if ($isNetto) {
                            $bruttoSubSum = $subSum * ($vatValue / 100 + 1);
                            $percentage   = $priceFactorValue / 100 * $bruttoSubSum;
                        } else {
                            $percentage = $priceFactorValue / 100 * $subSum;
                        }
                        break;
                }

                $percentage = \round($percentage, $precision);
                $vatSum     = \round($PriceFactor->getVatSum(), $precision);

                // set netto sum
                $PriceFactor->setNettoSum($percentage);

                if ($isNetto) {
                    $PriceFactor->setSum($PriceFactor->getNettoSum());
                } else {
                    $PriceFactor->setSum($vatSum + $PriceFactor->getNettoSum());
                }

                // formatted
                $PriceFactor->setNettoSumFormatted($Currency->format($PriceFactor->getNettoSum()));
                $PriceFactor->setSumFormatted($Currency->format($PriceFactor->getSum()));
            }

            $nettoSum       = $nettoSum + $PriceFactor->getNettoSum();
            $priceFactorSum = $priceFactorSum + $PriceFactor->getNettoSum();

            if ($isEuVatUser) {
                $PriceFactor->setEuVatStatus(true);
            }

            $vat    = $PriceFactor->getVat();
            $vatSum = \round($PriceFactor->getVatSum(), $precision);

            if (!isset($vatArray[$vat])) {
                $vatArray[$vat] = [
                    'vat'  => $vat,
                    'text' => self::getVatText($vat, $this->getUser())
                ];

                $vatArray[$vat]['sum'] = 0;
            }

            $vatArray[$vat]['sum'] = $vatArray[$vat]['sum'] + $vatSum;
        }

        if ($isEuVatUser) {
            $vatArray = [];
        }

        // vat text
        $vatLists = [];
        $vatText  = [];

        $nettoSum    = \round($nettoSum, $precision);
        $nettoSubSum = \round($nettoSubSum, $precision);
        $subSum      = \round($subSum, $precision);
        $bruttoSum   = $nettoSum;

        foreach ($vatArray as $vatEntry) {
            $vat = $vatEntry['vat'];

            $vatLists[$vat]        = true; // liste für MWST texte
            $vatArray[$vat]['sum'] = \round($vatEntry['sum'], $precision);

            $bruttoSum = $bruttoSum + $vatArray[$vat]['sum'];
        }

        $bruttoSum = \round($bruttoSum, $precision);

        foreach ($vatLists as $vat => $bool) {
            $vatText[$vat] = self::getVatText($vat, $this->getUser());
        }

        // delete 0 % vat, 0% vat is allowed to calculate more easily
        if (isset($vatText[0])) {
            unset($vatText[0]);
        }

        if (isset($vatArray[0])) {
            unset($vatArray[0]);
        }


        // gegenrechnung, wegen rundungsfehler
        if ($isNetto === false) {
            $priceFactorBruttoSums = 0;

            foreach ($priceFactors as $Factor) {
                /* @var $Factor QUI\ERP\Products\Utils\PriceFactor */
                $priceFactorBruttoSums = $priceFactorBruttoSums + \round($Factor->getSum(), $precision);
            }

            $priceFactorBruttoSum = $subSum + $priceFactorBruttoSums;
            $priceFactorBruttoSum = \round($priceFactorBruttoSum, $precision);

            if ($priceFactorBruttoSum !== \round($bruttoSum, $precision)) {
                $diff = $priceFactorBruttoSum - \round($bruttoSum, $precision);

                // if we have a diff, we change the first vat price factor
                foreach ($priceFactors as $Factor) {
                    if ($Factor instanceof QUI\ERP\Products\Interfaces\PriceFactorWithVatInterface) {
                        $Factor->setSum(\round($Factor->getSum() - $diff, $precision));
                        $bruttoSum = \round($bruttoSum, $precision);
                        break;
                    }
                }
            }
        }

        if ($bruttoSum === 0 || $nettoSum === 0) {
            $bruttoSum = 0;
            $nettoSum  = 0;

            foreach ($vatArray as $vat => $entry) {
                $vatArray[$vat]['sum'] = 0;
            }
        }

        $callback([
            'sum'          => $bruttoSum,
            'subSum'       => $subSum,
            'nettoSum'     => $nettoSum,
            'nettoSubSum'  => $nettoSubSum,
            'vatArray'     => $vatArray,
            'vatText'      => $vatText,
            'isEuVat'      => $isEuVatUser,
            'isNetto'      => $isNetto,
            'currencyData' => $this->getCurrency()->toArray()
        ]);

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
        if (!\is_callable($callback)) {
            $Article->calc($this);

            return $Article->getPrice();
        }

        $isNetto     = QUI\ERP\Utils\User::isNettoUser($this->getUser());
        $isEuVatUser = QUI\ERP\Tax\Utils::isUserEuVatUser($this->getUser());

        $nettoPrice      = $Article->getUnitPrice()->value();
        $vat             = $Article->getVat();
        $basisNettoPrice = $nettoPrice;
        $nettoSubSum     = $this->round($nettoPrice * $Article->getQuantity());

        if ($isEuVatUser) {
            $vat = 0;
        }

        // discounts
        $Discount = $Article->getDiscount();

        if ($Discount) {
            switch ($Discount->getCalculation()) {
                // einfache Zahl, Währung --- kein Prozent
                case Calc::CALCULATION_COMPLEMENT:
                    $nettoPrice = $nettoPrice - ($Discount->getValue() / $Article->getQuantity());
                    break;

                // Prozent Angabe
                case Calc::CALCULATION_PERCENTAGE:
                    $percentage = $Discount->getValue() / 100 * $nettoPrice;
                    $nettoPrice = $nettoPrice - $percentage;
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

        $vatArray = [
            'vat'  => $vat,
            'sum'  => $this->round($nettoSum * ($vat / 100)),
            'text' => $this->getVatText($vat, $this->getUser())
        ];

        QUI\ERP\Debug::getInstance()->log(
            'Kalkulierter Artikel Preis '.$Article->getId(),
            'quiqqer/erp'
        );

        $data = [
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
        ];

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
        $precision         = QUI\ERP\Defaults::getPrecision();

        if (\strpos($value, $decimalSeparator) && $decimalSeparator != '.') {
            $value = \str_replace($groupingSeparator, '', $value);
        }

        $value = \str_replace(',', '.', $value);
        $value = \floatval($value);
        $value = \round($value, $precision);

        return $value;
    }

    /**
     * Return the tax message for an user
     *
     * @return string
     */
    public function getVatTextByUser()
    {
        try {
            $Tax = QUI\ERP\Tax\Utils::getTaxByUser($this->getUser());
        } catch (QUI\Exception $Exception) {
            return '';
        }

        return $this->getVatText($Tax->getValue(), $this->getUser());
    }

    /**
     * Return tax text
     * eq: incl or zzgl
     *
     * @param integer $vat
     * @param UserInterface $User
     * @param null|QUI\Locale $Locale - optional
     *
     * @return array|string
     */
    public static function getVatText($vat, UserInterface $User, $Locale = null)
    {
        if ($Locale === null) {
            $Locale = $User->getLocale();
        }

        if (QUI\ERP\Utils\User::isNettoUser($User)) {
            if (QUI\ERP\Tax\Utils::isUserEuVatUser($User)) {
                return $Locale->get(
                    'quiqqer/tax',
                    'message.vat.text.netto.EUVAT',
                    ['vat' => $vat]
                );
            }

            // vat ist leer und kein EU vat user
            if (!$vat) {
                return '';
            }

            return $Locale->get(
                'quiqqer/tax',
                'message.vat.text.netto',
                ['vat' => $vat]
            );
        }

        if (QUI\ERP\Tax\Utils::isUserEuVatUser($User)) {
            return $Locale->get(
                'quiqqer/tax',
                'message.vat.text.brutto.EUVAT',
                ['vat' => $vat]
            );
        }

        // vat ist leer und kein EU vat user
        if (!$vat) {
            return '';
        }

        return $Locale->get(
            'quiqqer/tax',
            'message.vat.text.brutto',
            ['vat' => $vat]
        );
    }

    /**
     * Calculates the individual amounts paid of an invoice
     *
     * @param Invoice $Invoice
     * @return array
     *
     * @throws QUI\ERP\Exception
     *
     * @deprecated use calculatePayments
     */
    public static function calculateInvoicePayments(Invoice $Invoice)
    {
        return self::calculatePayments($Invoice);
    }

    /**
     * Calculates the individual amounts paid of an invoice / order
     *
     * @param InvoiceTemporary|Invoice|QUI\ERP\Order\AbstractOrder $ToCalculate
     * @return array
     *
     * @throws QUI\ERP\Exception
     */
    public static function calculatePayments($ToCalculate)
    {
        if (self::isAllowedForCalculation($ToCalculate) === false) {
            QUI\ERP\Debug::getInstance()->log(
                'Calc->calculatePayments(); Object is not allowed to calculate '.\get_class($ToCalculate)
            );

            throw new QUI\ERP\Exception('Object is not allowed to calculate');
        }

        QUI\ERP\Debug::getInstance()->log(
            'Calc->calculatePayments(); Transaction'
        );

        // if payment status is paid, take it immediately and do not query any transactions
        if ($ToCalculate->getAttribute('paid_status') === Invoice::PAYMENT_STATUS_PAID) {
            $paidData = $ToCalculate->getAttribute('paid_data');
            $paid     = 0;

            if (!\is_array($paidData)) {
                $paidData = \json_decode($paidData, true);
            }

            if (!\is_array($paidData)) {
                $paidData = [];
            }

            foreach ($paidData as $entry) {
                if (isset($entry['amount'])) {
                    $paid = $paid + \floatval($entry['amount']);
                }
            }

            $ToCalculate->setAttribute('paid', $paid);
            $ToCalculate->setAttribute('toPay', 0);

            QUI\ERP\Debug::getInstance()->log([
                'paidData'   => $ToCalculate->getAttribute('paid_data'),
                'paidDate'   => $ToCalculate->getAttribute('paid_date'),
                'paidStatus' => $ToCalculate->getAttribute('paid_status'),
                'paid'       => $ToCalculate->getAttribute('paid'),
                'toPay'      => $ToCalculate->getAttribute('toPay')
            ]);

            return [
                'paidData'   => $ToCalculate->getAttribute('paid_data'),
                'paidDate'   => $ToCalculate->getAttribute('paid_date'),
                'paidStatus' => $ToCalculate->getAttribute('paid_status'),
                'paid'       => $ToCalculate->getAttribute('paid'),
                'toPay'      => $ToCalculate->getAttribute('toPay')
            ];
        }


        // calc with transactions
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($ToCalculate->getHash());
        $calculations = $ToCalculate->getArticles()->getCalculations();

        if (!isset($calculations['sum'])) {
            $calculations['sum'] = 0;
        }

        $paidData = [];
        $paidDate = 0;
        $sum      = 0;
        $total    = $calculations['sum'];

        QUI\ERP\Debug::getInstance()->log(
            'Calc->calculatePayments(); total: '.$total
        );

        $isValidTimeStamp = function ($timestamp) {
            try {
                new \DateTime('@'.$timestamp);
            } catch (\Exception $e) {
                return false;
            }

            return true;
        };

        foreach ($transactions as $Transaction) {
            /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
            if (!$Transaction->isComplete()) {
                // don't add incomplete transactions
                continue;
            }

            // calculate the paid amount
            $amount = Price::validatePrice($Transaction->getAmount());

            // set the newest date
            $date = $Transaction->getDate();

            if ($isValidTimeStamp($date) === false) {
                $date = \strtotime($date);

                if ($isValidTimeStamp($date) === false) {
                    $date = \time();
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

            $paidData[] = [
                'amount' => $amount,
                'date'   => $date,
                'txid'   => $Transaction->getTxId()
            ];
        }

        $paid  = Price::validatePrice($sum);
        $toPay = Price::validatePrice($calculations['sum']);

        // workaround fix
        if ($ToCalculate->getAttribute('paid_date') != $paidDate) {
            try {
                QUI::getDataBase()->update(
                    Handler::getInstance()->invoiceTable(),
                    ['paid_date' => $paidDate],
                    ['id' => $ToCalculate->getCleanId()]
                );
            } catch (QUI\Database\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                throw new QUI\ERP\Exception(
                    ['quiqqer/erp', 'exception.something.went.wrong'],
                    $Exception->getCode()
                );
            }
        }

        $ToCalculate->setAttribute('paid_data', \json_encode($paidData));
        $ToCalculate->setAttribute('paid_date', $paidDate);
        $ToCalculate->setAttribute('paid', $sum);
        $ToCalculate->setAttribute('toPay', $toPay - $paid);

        if ($ToCalculate instanceof QUI\ERP\Order\AbstractOrder
            && $ToCalculate->getAttribute('paid_status') === QUI\ERP\Order\Order::PAYMENT_STATUS_PLAN) {
            // Leave everything as it is because a subscription plan order can never be set to "paid"
        } elseif ($ToCalculate->getAttribute('paid_status') === Handler::TYPE_INVOICE_REVERSAL
                  || $ToCalculate->getAttribute('paid_status') === Handler::TYPE_INVOICE_CANCEL
        ) {
            // Leave everything as it is
        } elseif ((float)$ToCalculate->getAttribute('toPay') == 0) {
            $ToCalculate->setAttribute('paid_status', Invoice::PAYMENT_STATUS_PAID);
        } elseif ($ToCalculate->getAttribute('paid') == 0) {
            $ToCalculate->setAttribute('paid_status', Invoice::PAYMENT_STATUS_OPEN);
        } elseif ($ToCalculate->getAttribute('toPay')
                  && $calculations['sum'] != $ToCalculate->getAttribute('paid')
        ) {
            $ToCalculate->setAttribute('paid_status', Invoice::PAYMENT_STATUS_PART);
        }

        QUI\ERP\Debug::getInstance()->log([
            'paidData'   => $paidData,
            'paidDate'   => $ToCalculate->getAttribute('paid_date'),
            'paid'       => $ToCalculate->getAttribute('paid'),
            'toPay'      => $ToCalculate->getAttribute('toPay'),
            'paidStatus' => $ToCalculate->getAttribute('paid_status'),
            'sum'        => $sum
        ]);

        return [
            'paidData'   => $paidData,
            'paidDate'   => $ToCalculate->getAttribute('paid_date'),
            'paidStatus' => $ToCalculate->getAttribute('paid_status'),
            'paid'       => $ToCalculate->getAttribute('paid'),
            'toPay'      => $ToCalculate->getAttribute('toPay')
        ];
    }

    /**
     * Is the object allowed for calculation
     *
     * @param InvoiceTemporary|Invoice|QUI\ERP\Order\AbstractOrder $ToCalculate
     * @return bool
     */
    public static function isAllowedForCalculation($ToCalculate)
    {
        if ($ToCalculate instanceof Invoice) {
            return true;
        }

        if ($ToCalculate instanceof InvoiceTemporary) {
            return true;
        }

        if ($ToCalculate instanceof QUI\ERP\Order\AbstractOrder) {
            return true;
        }

        return false;
    }

    /**
     * Calculate the total of the invoice list
     *
     * @param array $invoiceList - list of invoice array
     * @param QUI\ERP\Currency\Currency|null $Currency
     * @return array
     */
    public static function calculateTotal(array $invoiceList, $Currency = null)
    {
        if ($Currency === null) {
            try {
                $currency = \json_decode($invoiceList[0]['currency_data'], true);
                $Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);
            } catch (QUI\Exception $Exception) {
                $Currency = QUI\ERP\Defaults::getCurrency();
            }
        }

        if (!\count($invoiceList)) {
            $display = $Currency->format(0);

            return [
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
            ];
        }

        $nettoTotal = 0;
        $vatTotal   = 0;

        $bruttoToPay = 0;
        $bruttoPaid  = 0;
        $bruttoTotal = 0;

        foreach ($invoiceList as $invoice) {
            if (isset($invoice['type']) && (int)$invoice['type'] === Handler::TYPE_INVOICE_CANCEL ||
                isset($invoice['type']) && (int)$invoice['type'] === Handler::TYPE_INVOICE_STORNO
            ) {
                continue;
            }

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

        return [
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
        ];
    }

    /**
     * Return the total of all vats
     *
     * @param string|array $vatArray
     * @return float|int
     */
    public static function calculateTotalVatOfInvoice($vatArray)
    {
        if (\is_string($vatArray)) {
            $vatArray = \json_decode($vatArray, true);
        }

        if (!\is_array($vatArray)) {
            return 0;
        }

        return \array_sum(
            \array_map(function ($vat) {
                return $vat['sum'];
            }, $vatArray)
        );
    }
}
