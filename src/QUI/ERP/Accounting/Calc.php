<?php

/**
 * This file contains QUI\ERP\Accounting\Calc
 */

namespace QUI\ERP\Accounting;

use DateTime;
use Exception;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Money\Price;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Locale;

use function array_map;
use function array_sum;
use function class_exists;
use function count;
use function floatval;
use function get_class;
use function is_array;
use function is_callable;
use function is_null;
use function is_string;
use function json_decode;
use function json_encode;
use function key;
use function round;
use function sprintf;
use function str_replace;
use function strpos;
use function strtotime;
use function time;

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
     * warning: it's not brutto VAT
     *
     * geht vnn der netto basis aus, welche alle price faktoren schon beinhaltet
     * alle felder sind in diesem price schon enthalten
     */
    const CALCULATION_BASIS_BRUTTO = 3;

    /**
     * Berechnet auf Basis des Preises inklusive Steuern
     * Zum Beispiel MwSt
     */
    const CALCULATION_BASIS_VAT_BRUTTO = 4;

    /**
     * Berechnet von Gesamtpreis
     */
    const CALCULATION_GRAND_TOTAL = 5;

    /**
     * Special transaction attributes for currency exchange
     */
    const TRANSACTION_ATTR_TARGET_CURRENCY = 'tx_target_currency';
    const TRANSACTION_ATTR_TARGET_CURRENCY_EXCHANGE_RATE = 'tx_target_currency_exchange_rate';
    const TRANSACTION_ATTR_SHOP_CURRENCY_EXCHANGE_RATE = 'tx_shop_currency_exchange_rate';

    protected ?UserInterface $User = null;

    protected ?QUI\Locale $Locale = null;

    protected ?QUI\ERP\Currency\Currency $Currency = null;

    /**
     * Calc constructor.
     *
     * @param UserInterface|null $User - calculation user
     */
    public function __construct(?UserInterface $User = null)
    {
        if (!QUI::getUsers()->isUser($User)) {
            $User = QUI::getUserBySession();
        }

        $this->User = $User;
        $this->Locale = QUI::getLocale();
    }

    /**
     * Static instance create
     *
     * @param UserInterface|null $User - optional
     * @return Calc
     */
    public static function getInstance(UserInterface $User = null): Calc
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
    public function setUser(UserInterface $User): void
    {
        $this->User = $User;
    }

    /**
     * Return the calc user
     *
     * @return UserInterface|null
     */
    public function getUser(): ?UserInterface
    {
        return $this->User;
    }

    //region locale

    public function getLocale(): ?Locale
    {
        return $this->Locale;
    }

    public function setLocale(QUI\Locale $Locale): void
    {
        $this->Locale = $Locale;
    }

    public function resetLocale(): void
    {
        $this->Locale = QUI::getLocale();
    }

    //endregion

    /**
     * Return the currency
     *
     * @return Currency|null
     */
    public function getCurrency(): ?QUI\ERP\Currency\Currency
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
    public function calcArticleList(ArticleList $List, callable|bool $callback = false): ArticleList
    {
        // calc data
        if (!is_callable($callback)) {
            return $List->calc();
        }

        // user order address
        $Order = $List->getOrder();

        if ($Order) {
            $this->getUser()->setAttribute('CurrentAddress', $Order->getDeliveryAddress());
        }

        $this->Currency = $List->getCurrency();

        $articles = $List->getArticles();
        $isNetto = QUI\ERP\Utils\User::isNettoUser($this->getUser());
        $isEuVatUser = QUI\ERP\Tax\Utils::isUserEuVatUser($this->getUser());

        $Currency = $this->getCurrency();
        $precision = $Currency->getPrecision();

        $subSum = 0;
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
            $calculated = $articleAttributes['calculated'];

            $subSum = $subSum + $calculated['sum'];
            $nettoSum = $nettoSum + $calculated['nettoSum'];

            $articleVatArray = $calculated['vatArray'];
            $vat = $articleAttributes['vat'];

            if ($articleVatArray['text'] === '') {
                continue;
            }

            if (!isset($vatArray[(string)$vat])) {
                $vatArray[(string)$vat] = $articleVatArray;
                $vatArray[(string)$vat]['sum'] = 0;
            }

            $vatArray[(string)$vat]['sum'] = $vatArray[(string)$vat]['sum'] + $articleVatArray['sum'];
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
        $priceFactors = $List->getPriceFactors();
        $priceFactorSum = 0;

        // nur wenn wir welche benötigen, für ERP Artikel ist dies im Moment nicht wirklich nötig
        $nettoSubSum = $nettoSum;

        /* @var $PriceFactor QUI\ERP\Accounting\PriceFactors\Factor */
        foreach ($priceFactors as $PriceFactor) {
            if ($PriceFactor->getCalculationBasis() === self::CALCULATION_GRAND_TOTAL) {
                $PriceFactor->setNettoSum($PriceFactor->getValue());
                $PriceFactor->setValueText('');
                continue;
            }

            if ($PriceFactor->getCalculation() === self::CALCULATION_COMPLEMENT) {
                // Standard calculation - Fester Preis
                $vatSum = $PriceFactor->getVatSum();

                if ($isNetto) {
                    $PriceFactor->setSum($PriceFactor->getNettoSum());
                } elseif ($PriceFactor->getCalculationBasis() === self::CALCULATION_BASIS_VAT_BRUTTO) {
                    $PriceFactor->setNettoSum($PriceFactor->getNettoSum() - $vatSum);
                    $PriceFactor->setSum($vatSum + $PriceFactor->getNettoSum());
                } else {
                    $PriceFactor->setSum($vatSum + $PriceFactor->getNettoSum());
                }

                // formatted
                $PriceFactor->setNettoSumFormatted($Currency->format($PriceFactor->getNettoSum()));
                $PriceFactor->setSumFormatted($Currency->format($PriceFactor->getSum()));
            } elseif ($PriceFactor->getCalculation() === self::CALCULATION_PERCENTAGE) {
                // percent - Prozent Angabe
                $calcBasis = $PriceFactor->getCalculationBasis();
                $priceFactorValue = $PriceFactor->getValue();
                $vatValue = $PriceFactor->getVat();

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
                            $percentage = $priceFactorValue / 100 * $bruttoSubSum;
                        } else {
                            $percentage = $priceFactorValue / 100 * $subSum;
                        }
                        break;

                    case self::CALCULATION_GRAND_TOTAL:
                        // starts later
                        continue 2;
                }

                $percentage = round($percentage, $precision);
                $vatSum = round($PriceFactor->getVatSum(), $precision);

                // set netto sum
                $PriceFactor->setNettoSum($percentage);

                if ($isNetto) {
                    $PriceFactor->setSum($PriceFactor->getNettoSum());
                } elseif ($PriceFactor->getCalculationBasis() === self::CALCULATION_BASIS_VAT_BRUTTO) {
                    $PriceFactor->setNettoSum($PriceFactor->getNettoSum() - $vatSum);
                    $PriceFactor->setSum($vatSum + $PriceFactor->getNettoSum());
                } else {
                    $PriceFactor->setSum($vatSum + $PriceFactor->getNettoSum());
                }

                // formatted
                $PriceFactor->setNettoSumFormatted($Currency->format($PriceFactor->getNettoSum()));
                $PriceFactor->setSumFormatted($Currency->format($PriceFactor->getSum()));
            } else {
                continue;
            }

            $nettoSum = $nettoSum + $PriceFactor->getNettoSum();
            $priceFactorSum = $priceFactorSum + $PriceFactor->getNettoSum();

            if ($isEuVatUser) {
                $PriceFactor->setEuVatStatus(true);
            }

            $vat = $PriceFactor->getVat();
            $vatSum = round($PriceFactor->getVatSum(), $precision);

            if (!isset($vatArray[(string)$vat])) {
                $vatArray[(string)$vat] = [
                    'vat' => $vat,
                    'text' => $this->getVatText($vat, $this->getUser(), $this->Locale)
                ];

                $vatArray[(string)$vat]['sum'] = 0;
            }

            $vatArray[(string)$vat]['sum'] = $vatArray[(string)$vat]['sum'] + $vatSum;
        }

        if ($isEuVatUser) {
            $vatArray = [];
        }

        // vat text
        $vatLists = [];
        $vatText = [];

        $nettoSum = round($nettoSum, $precision);
        $nettoSubSum = round($nettoSubSum, $precision);
        $subSum = round($subSum, $precision);
        $bruttoSum = $nettoSum;

        foreach ($vatArray as $vatEntry) {
            $vat = $vatEntry['vat'];

            $vatLists[(string)$vat] = true; // liste für MWST texte
            $vatArray[(string)$vat]['sum'] = round($vatEntry['sum'], $precision);

            $bruttoSum = $bruttoSum + $vatArray[(string)$vat]['sum'];
        }

        $bruttoSum = round($bruttoSum, $precision);

        foreach ($vatLists as $vat => $bool) {
            $vatText[(string)$vat] = $this->getVatText((float)$vat, $this->getUser(), $this->Locale);
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
                if ($Factor->getCalculationBasis() !== self::CALCULATION_GRAND_TOTAL) {
                    /* @var $Factor QUI\ERP\Products\Utils\PriceFactor */
                    $priceFactorBruttoSums = $priceFactorBruttoSums + round($Factor->getSum(), $precision);
                }
            }

            $priceFactorBruttoSum = $subSum + $priceFactorBruttoSums;
            $priceFactorBruttoSum = round($priceFactorBruttoSum, $precision);

            if ($priceFactorBruttoSum !== round($bruttoSum, $precision)) {
                $diff = $priceFactorBruttoSum - round($bruttoSum, $precision);

                // if we have a diff, we change the first vat price factor
                $added = false;

                foreach ($priceFactors as $Factor) {
                    if ($Factor->getCalculationBasis() === self::CALCULATION_GRAND_TOTAL) {
                        continue;
                    }

                    if ($Factor instanceof QUI\ERP\Products\Interfaces\PriceFactorWithVatInterface) {
                        $Factor->setSum(round($Factor->getSum() - $diff, $precision));
                        $bruttoSum = round($bruttoSum, $precision);
                        $added = true;
                        break;
                    }
                }

                if ($added === false) {
                    $bruttoSum = $bruttoSum + $diff;

                    // netto check 1cent check
                    $bruttoVatSum = 0;

                    foreach ($vatArray as $data) {
                        $bruttoVatSum = $bruttoVatSum + $data['sum'];
                    }

                    if ($bruttoSum - $bruttoVatSum !== $nettoSum) {
                        $nettoSum = $nettoSum + $diff;
                    }
                }
            }


            // counterbalance - gegenrechnung
            // works only for one vat entry
            if (count($vatArray) === 1 && $isNetto) {
                $vat = key($vatArray);
                $netto = $bruttoSum / ((float)$vat / 100 + 1);

                $vatSum = $bruttoSum - $netto;
                $vatSum = round($vatSum, $Currency->getPrecision());
                $diff = abs($vatArray[(string)$vat]['sum'] - $vatSum);

                if ($diff <= 0.019) {
                    $vatArray[(string)$vat]['sum'] = $vatSum;
                }
            }
        }

        if (empty($bruttoSum) || empty($nettoSum)) {
            $bruttoSum = 0;
            $nettoSum = 0;

            foreach ($vatArray as $vat => $entry) {
                $vatArray[(string)$vat]['sum'] = 0;
            }
        }

        // look if CALCULATION_GRAND_TOTAL
        $grandSubSum = $bruttoSum;

        foreach ($priceFactors as $Factor) {
            if ($Factor->getCalculationBasis() === self::CALCULATION_GRAND_TOTAL) {
                $value = $Factor->getValue();
                $bruttoSum = $bruttoSum + $value;

                if ($bruttoSum < 0) {
                    $bruttoSum = 0;
                }
            }
        }

        $callback([
            'sum' => $bruttoSum,
            'subSum' => $subSum,
            'grandSubSum' => $grandSubSum,
            'nettoSum' => $nettoSum,
            'nettoSubSum' => $nettoSubSum,
            'vatArray' => $vatArray,
            'vatText' => $vatText,
            'isEuVat' => $isEuVatUser,
            'isNetto' => $isNetto,
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
        if (!is_callable($callback)) {
            $Article->calc($this);

            return $Article->getPrice();
        }

        $isNetto = QUI\ERP\Utils\User::isNettoUser($this->getUser());
        $isEuVatUser = QUI\ERP\Tax\Utils::isUserEuVatUser($this->getUser());
        $Currency = $Article->getCurrency();

        if (!$Currency) {
            $Currency = $this->getCurrency();
        }

        $nettoPrice = $Article->getUnitPriceUnRounded()->value();
        $nettoPrice = round($nettoPrice, $Currency->getPrecision());
        $nettoPriceNotRounded = $Article->getUnitPriceUnRounded()->getValue();

        $vat = $Article->getVat();
        $quantity = $Article->getQuantity();

        $basisNettoPrice = $nettoPrice;
        $nettoSubSum = $this->round($nettoPrice * $Article->getQuantity());

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
                    $nettoPriceNotRounded = $nettoPriceNotRounded - ($Discount->getValue() / $Article->getQuantity());
                    break;

                // Prozent Angabe
                case Calc::CALCULATION_PERCENTAGE:
                    $percentage = $Discount->getValue() / 100 * $nettoPrice;
                    $nettoPrice = $nettoPrice - $percentage;
                    $nettoPriceNotRounded = $nettoPriceNotRounded - $percentage;
                    break;
            }
        }

        $vatSum = $nettoPrice * ($vat / 100);
        $precision = $Currency->getPrecision();
        $vatSum = round($vatSum, $precision);

        $priceSum = $nettoPrice + $vatSum;
        $bruttoPrice = round($priceSum, $precision);

        if (!$isNetto) {
            // korrektur rechnung / 1 cent problem
            $checkBrutto = $nettoPriceNotRounded * ($vat / 100 + 1);
            $checkBrutto = round($checkBrutto, $Currency->getPrecision());
            $checkVat = $checkBrutto - $nettoPriceNotRounded;

            if ($nettoPrice + $checkVat !== $checkBrutto) {
                $diff = round(
                    $nettoPrice + $checkVat - $checkBrutto,
                    $Currency->getPrecision()
                );

                $checkVat = $checkVat - $diff;
            }

            // sum
            $checkVat = round($checkVat * $Article->getQuantity(), $Currency->getPrecision());
            $nettoSum = $this->round($nettoPrice * $Article->getQuantity());
            $vatSum = $nettoSum * ($vat / 100);

            // korrektur rechnung / 1 cent problem
            if ($checkBrutto !== $bruttoPrice) {
                $bruttoPrice = $checkBrutto;
                $vatSum = $checkVat;
            }

            // Related: pcsg/buero#344
            // Related: pcsg/buero#436
            if ($nettoSum + $checkVat !== $bruttoPrice * $quantity) {
                $diff = $nettoSum + $checkVat - ($bruttoPrice * $quantity);

                $vatSum = $vatSum - $diff;
                $vatSum = round($vatSum, $precision);
            }

            // if the user is brutto
            // and we have a quantity
            // we need to calc first the brutto product price of one product
            // -> because of 1 cent rounding error
            $bruttoSum = $bruttoPrice * $Article->getQuantity();
        } else {
            // sum
            $nettoSum = $this->round($nettoPrice * $Article->getQuantity());
            $vatSum = $nettoSum * ($vat / 100);

            $bruttoSum = $this->round($nettoSum + $vatSum);
        }

        $price = $isNetto ? $nettoPrice : $bruttoPrice;
        $sum = $isNetto ? $nettoSum : $bruttoSum;
        $basisPrice = $isNetto ? $basisNettoPrice : $basisNettoPrice + ($basisNettoPrice * $vat / 100);
        $basisPrice = round($basisPrice, QUI\ERP\Defaults::getPrecision());

        $vatArray = [
            'vat' => $vat,
            'sum' => $vatSum,
            'text' => $this->getVatText($vat, $this->getUser(), $this->Locale)
        ];

        QUI\ERP\Debug::getInstance()->log(
            'Kalkulierter Artikel Preis ' . $Article->getId(),
            'quiqqer/erp'
        );

        $data = [
            'basisPrice' => $basisPrice,
            'price' => $price,
            'sum' => $sum,

            'nettoBasisPrice' => $basisNettoPrice,
            'nettoPrice' => $nettoPrice,
            'nettoSubSum' => $nettoSubSum,
            'nettoSum' => $nettoSum,

            'currencyData' => $this->getCurrency()->toArray(),
            'vatArray' => $vatArray,
            'vatText' => $vatArray['text'],
            'isEuVat' => $isEuVatUser,
            'isNetto' => $isNetto
        ];

        QUI\ERP\Debug::getInstance()->log($data, 'quiqqer/erp');

        $callback($data);

        return $Article->getPrice();
    }

    /**
     * Rounds the value via shop config
     *
     * @param string|int|float $value
     * @return float
     */
    public function round($value): float
    {
        $decimalSeparator = $this->getUser()->getLocale()->getDecimalSeparator();
        $groupingSeparator = $this->getUser()->getLocale()->getGroupingSeparator();
        $precision = QUI\ERP\Defaults::getPrecision();

        if (strpos($value, $decimalSeparator) && $decimalSeparator != '.') {
            $value = str_replace($groupingSeparator, '', $value);
        }

        $value = str_replace(',', '.', $value);
        $value = floatval($value);
        $value = round($value, $precision);

        return $value;
    }

    /**
     * Return the tax message for an user
     *
     * @return string
     */
    public function getVatTextByUser(): string
    {
        try {
            $Tax = QUI\ERP\Tax\Utils::getTaxByUser($this->getUser());
        } catch (QUI\Exception) {
            return '';
        }

        return $this->getVatText($Tax->getValue(), $this->getUser(), $this->Locale);
    }

    /**
     * Return tax text
     * eq: incl or zzgl
     *
     * @param float|int $vat
     * @param UserInterface $User
     * @param null|Locale $Locale - optional
     *
     * @return string
     */
    public static function getVatText(
        float|int $vat,
        UserInterface $User,
        QUI\Locale $Locale = null
    ): string {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
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
    public static function calculateInvoicePayments(Invoice $Invoice): array
    {
        return self::calculatePayments($Invoice);
    }

    /**
     * Calculates the individual amounts paid of an invoice / order
     *
     * @param mixed $ToCalculate
     * @return array
     *
     * @throws QUI\ERP\Exception|QUI\Exception
     */
    public static function calculatePayments($ToCalculate): array
    {
        if (self::isAllowedForCalculation($ToCalculate) === false) {
            QUI\ERP\Debug::getInstance()->log(
                'Calc->calculatePayments(); Object is not allowed to calculate ' . get_class($ToCalculate)
            );

            throw new QUI\ERP\Exception('Object is not allowed to calculate ' . get_class($ToCalculate));
        }

        QUI\ERP\Debug::getInstance()->log(
            'Calc->calculatePayments(); Transaction'
        );

        // if payment status is paid, take it immediately and do not query any transactions
        if ($ToCalculate->getAttribute('paid_status') === QUI\ERP\Constants::PAYMENT_STATUS_PAID) {
            $paidData = $ToCalculate->getAttribute('paid_data');
            $paid = 0;

            if (!is_array($paidData)) {
                $paidData = json_decode($paidData, true);
            }

            if (!is_array($paidData)) {
                $paidData = [];
            }

            foreach ($paidData as $entry) {
                if (isset($entry['amount'])) {
                    $paid = $paid + floatval($entry['amount']);
                }
            }

            $ToCalculate->setAttribute('paid', $paid);
            $ToCalculate->setAttribute('toPay', 0);

            QUI\ERP\Debug::getInstance()->log([
                'paidData' => $ToCalculate->getAttribute('paid_data'),
                'paidDate' => $ToCalculate->getAttribute('paid_date'),
                'paidStatus' => $ToCalculate->getAttribute('paid_status'),
                'paid' => $ToCalculate->getAttribute('paid'),
                'toPay' => $ToCalculate->getAttribute('toPay')
            ]);

            return [
                'paidData' => $ToCalculate->getAttribute('paid_data'),
                'paidDate' => $ToCalculate->getAttribute('paid_date'),
                'paidStatus' => $ToCalculate->getAttribute('paid_status'),
                'paid' => $ToCalculate->getAttribute('paid'),
                'toPay' => $ToCalculate->getAttribute('toPay')
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
        $sum = 0;
        $total = $calculations['sum'];

        QUI\ERP\Debug::getInstance()->log(
            'Calc->calculatePayments(); total: ' . $total
        );

        $isValidTimeStamp = function ($timestamp) {
            try {
                new DateTime('@' . $timestamp);
            } catch (Exception $e) {
                return false;
            }

            return true;
        };

        $CalculateCurrency = $ToCalculate->getCurrency();
        $ShopCurrency = QUI\ERP\Defaults::getCurrency();

        foreach ($transactions as $Transaction) {
            if (!$Transaction->isComplete()) {
                // don't add incomplete transactions
                continue;
            }

            // calculate the paid amount
            $amount = Price::validatePrice($Transaction->getAmount());
            $TransactionCurrency = $Transaction->getCurrency();

            // If necessary, convert from transaction currency to calculation object currency
            if ($CalculateCurrency->getCode() !== $TransactionCurrency->getCode()) {
                $targetCurrencyCode = $Transaction->getData(
                    self::TRANSACTION_ATTR_TARGET_CURRENCY
                );

                $targetCurrencyExchangeRate = $Transaction->getData(
                    self::TRANSACTION_ATTR_TARGET_CURRENCY_EXCHANGE_RATE
                );

                $shopCurrencyExchangeRate = $Transaction->getData(
                    self::TRANSACTION_ATTR_SHOP_CURRENCY_EXCHANGE_RATE
                );

                /*
                 * $amount has to DIVIDED by the exchange rate because the exchange rate is always
                 * in relation to the base (shop) currency to the given currency.
                 *
                 * Example: From ETH to EUR -> The exchange rate here is the rate that turn EUR into ETH; so to
                 * get ETH to EUR you have to divide the ETH value by the exchange rate.
                 */
                if ($targetCurrencyCode === $CalculateCurrency->getCode() && $targetCurrencyExchangeRate) {
                    $amount /= $targetCurrencyExchangeRate;
                } elseif ($ShopCurrency === $CalculateCurrency->getCode() && $shopCurrencyExchangeRate) {
                    $amount /= $shopCurrencyExchangeRate;
                } else {
                    $amount = $TransactionCurrency->convert($amount, $CalculateCurrency);

                    QUI\System\Log::addWarning(
                        sprintf(
                            'The currency of transaction "%s" for calculation of object %s (%s) is "%s" and differs'
                            . ' from the currency of the calculation object ("%s"). But the transaction does not'
                            . ' contain an exchange rate from "%s" to "%s". Thus, the exchange rate that is currently'
                            . ' live in the system is used for converting from "%s" to "%s".',
                            $Transaction->getTxId(),
                            $ToCalculate->getId(),
                            get_class($ToCalculate),
                            $TransactionCurrency->getCode(),
                            $CalculateCurrency->getCode(),
                            $TransactionCurrency->getCode(),
                            $CalculateCurrency->getCode(),
                            $TransactionCurrency->getCode(),
                            $CalculateCurrency->getCode()
                        )
                    );
                }

                $amount = $CalculateCurrency->amount($amount);
            }

            // set the newest date
            $date = $Transaction->getDate();

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

            $paidData[] = [
                'amount' => $amount,
                'date' => $date,
                'txid' => $Transaction->getTxId()
            ];
        }

        $paid = Price::validatePrice($sum);
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

        $ToCalculate->setAttribute('paid_data', json_encode($paidData));
        $ToCalculate->setAttribute('paid_date', $paidDate);
        $ToCalculate->setAttribute('paid', $sum);
        $ToCalculate->setAttribute('toPay', $toPay - $paid);

        if (
            $ToCalculate instanceof QUI\ERP\Order\AbstractOrder
            && $ToCalculate->getAttribute('paid_status') === QUI\ERP\Constants::PAYMENT_STATUS_PLAN
        ) {
            // Leave everything as it is because a subscription plan order can never be set to "paid"
        } elseif (
            $ToCalculate->getAttribute('paid_status') === QUI\ERP\Constants::TYPE_INVOICE_REVERSAL
            || $ToCalculate->getAttribute('paid_status') === QUI\ERP\Constants::TYPE_INVOICE_CANCEL
            || $ToCalculate->getAttribute('paid_status') === QUI\ERP\Constants::PAYMENT_STATUS_DEBIT
        ) {
            // Leave everything as it is
        } elseif ((float)$ToCalculate->getAttribute('toPay') == 0) {
            $ToCalculate->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_PAID);
        } elseif ($ToCalculate->getAttribute('paid') == 0) {
            $ToCalculate->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
        } elseif (
            $ToCalculate->getAttribute('toPay')
            && $calculations['sum'] != $ToCalculate->getAttribute('paid')
        ) {
            $ToCalculate->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_PART);
        }

        QUI\ERP\Debug::getInstance()->log([
            'paidData' => $paidData,
            'paidDate' => $ToCalculate->getAttribute('paid_date'),
            'paid' => $ToCalculate->getAttribute('paid'),
            'toPay' => $ToCalculate->getAttribute('toPay'),
            'paidStatus' => $ToCalculate->getAttribute('paid_status'),
            'sum' => $sum
        ]);

        return [
            'paidData' => $paidData,
            'paidDate' => $ToCalculate->getAttribute('paid_date'),
            'paidStatus' => $ToCalculate->getAttribute('paid_status'),
            'paid' => $ToCalculate->getAttribute('paid'),
            'toPay' => $ToCalculate->getAttribute('toPay')
        ];
    }

    /**
     * Is the object allowed for calculation
     *
     * @param mixed $ToCalculate
     * @return bool
     */
    public static function isAllowedForCalculation(mixed $ToCalculate): bool
    {
        if ($ToCalculate instanceof QUI\ERP\ErpEntityInterface) {
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
    public static function calculateTotal(array $invoiceList, QUI\ERP\Currency\Currency $Currency = null): array
    {
        if ($Currency === null) {
            try {
                $currency = json_decode($invoiceList[0]['currency_data'], true);
                $Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);
            } catch (QUI\Exception $Exception) {
                $Currency = QUI\ERP\Defaults::getCurrency();
            }
        }

        if (!count($invoiceList)) {
            $display = $Currency->format(0);

            return [
                'netto_toPay' => 0,
                'netto_paid' => 0,
                'netto_total' => 0,
                'display_netto_toPay' => $display,
                'display_netto_paid' => $display,
                'display_netto_total' => $display,

                'vat_toPay' => 0,
                'vat_paid' => 0,
                'vat_total' => 0,
                'display_vat_toPay' => $display,
                'display_vat_paid' => $display,
                'display_vat_total' => $display,

                'brutto_toPay' => 0,
                'brutto_paid' => 0,
                'brutto_total' => 0,
                'display_brutto_toPay' => $display,
                'display_brutto_paid' => $display,
                'display_brutto_total' => $display
            ];
        }

        $nettoTotal = 0;
        $vatTotal = 0;

        $bruttoToPay = 0;
        $bruttoPaid = 0;
        $bruttoTotal = 0;
        $vatPaid = 0;
        $nettoToPay = 0;

        foreach ($invoiceList as $invoice) {
//            if (isset($invoice['type']) && (int)$invoice['type'] === Handler::TYPE_INVOICE_CANCEL ||
//                isset($invoice['type']) && (int)$invoice['type'] === Handler::TYPE_INVOICE_STORNO
//            ) {
//                continue;
//            }
//          soll doch mit berechnet werden

            $invBruttoSum = floatval($invoice['calculated_sum']);
            $invVatSum = floatval($invoice['calculated_vatsum']);
            $invPaid = floatval($invoice['calculated_paid']);
            $invToPay = floatval($invoice['calculated_toPay']);
            $invNettoTotal = floatval($invoice['calculated_nettosum']);
            $invVatSumPC = QUI\Utils\Math::percent($invVatSum, $invBruttoSum);

            $invBruttoSum = round($invBruttoSum, $Currency->getPrecision());
            $invVatSum = round($invVatSum, $Currency->getPrecision());
            $invPaid = round($invPaid, $Currency->getPrecision());
            $invToPay = round($invToPay, $Currency->getPrecision());
            $invNettoTotal = round($invNettoTotal, $Currency->getPrecision());

            if ($invoice['paid_status'] === QUI\ERP\Constants::PAYMENT_STATUS_PAID) {
                $invPaid = $invBruttoSum;
            }

            if ($invVatSumPC) {
                if ($invToPay === 0.0) {
                    $invVatPaid = $invVatSum;
                } else {
                    $invVatPaid = round($invPaid * $invVatSumPC / 100, $Currency->getPrecision());
                }
            } else {
                $invVatPaid = 0;
            }

            $invNettoPaid = $invPaid - $invVatPaid;
            $invNettoToPay = $invNettoTotal - $invNettoPaid;

            if ($invToPay === 0.0) {
                $invNettoToPay = 0;
            }

            // complete + addition
            $vatPaid = $vatPaid + $invVatPaid;
            $bruttoTotal = $bruttoTotal + $invBruttoSum;
            $bruttoPaid = $bruttoPaid + $invPaid;
            //$bruttoToPay = $bruttoToPay + $invToPay;
            $nettoToPay = $nettoToPay + $invNettoToPay;
            $vatTotal = $vatTotal + $invVatSum;

            $nettoTotal = $nettoTotal + $invNettoTotal;
        }


        // netto calculation
        $nettoPaid = $bruttoPaid - $vatPaid;

        // vat calculation
        $vatToPay = $vatTotal - $vatPaid;
        $bruttoToPay = $bruttoTotal - $bruttoPaid;

        return [
            'netto_toPay' => $nettoToPay,
            'netto_paid' => $nettoPaid,
            'netto_total' => $nettoTotal,
            'display_netto_toPay' => $Currency->format($nettoToPay),
            'display_netto_paid' => $Currency->format($nettoPaid),
            'display_netto_total' => $Currency->format($nettoTotal),

            'vat_toPay' => $nettoPaid,
            'vat_paid' => $vatPaid,
            'vat_total' => $vatTotal,
            'display_vat_toPay' => $Currency->format($vatToPay),
            'display_vat_paid' => $Currency->format($vatPaid),
            'display_vat_total' => $Currency->format($vatTotal),

            'brutto_toPay' => $bruttoToPay,
            'brutto_paid' => $bruttoPaid,
            'brutto_total' => $bruttoTotal,
            'display_brutto_toPay' => $Currency->format($bruttoToPay),
            'display_brutto_paid' => $Currency->format($bruttoPaid),
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
        if (is_string($vatArray)) {
            $vatArray = json_decode($vatArray, true);
        }

        if (!is_array($vatArray)) {
            return 0;
        }

        return array_sum(
            array_map(function ($vat) {
                return $vat['sum'];
            }, $vatArray)
        );
    }
}
