<?php

/**
 * This file contains QUI\ERP\Money\Price
 */

namespace QUI\ERP\Money;

use QUI;
use QUI\ERP\Discount\Discount;

/**
 * Class Price
 * @package QUI\ERP\Products\Price
 */
class Price
{
    /**
     * Netto Price
     * @var float
     */
    protected $netto;

    /**
     * Price currency
     * @var QUI\ERP\Currency\Currency
     */
    protected $Currency;

    /**
     * @var bool
     */
    protected $startingPrice = false;

    /**
     * @var array
     */
    protected $discounts;

    /**
     * User
     * @var bool|QUI\Users\User
     */
    protected $User;

    /**
     * @var string
     */
    protected static $decimalSeparator = ',';

    /**
     * @var string
     */
    protected static $thousandsSeparator = '.';

    /**
     * Price constructor.
     *
     * @param float|int|double|string $nettoPrice
     * @param QUI\ERP\Currency\Currency $Currency
     * @param QUI\Users\User|boolean $User - optional, if no user, session user are used
     */
    public function __construct($nettoPrice, QUI\ERP\Currency\Currency $Currency, $User = false)
    {
        $this->netto    = $nettoPrice;
        $this->Currency = $Currency;

        $this->User      = $User;
        $this->discounts = [];

        if (!QUI::getUsers()->isUser($User)) {
            $this->User = QUI::getUserBySession();
        }
    }

    /**
     * Return the price as array notation
     * @return array
     */
    public function toArray()
    {
        return [
            'price'         => $this->getNetto(),
            'currency'      => $this->getCurrency()->getCode(),
            'display'       => $this->getDisplayPrice(),
            'startingprice' => $this->isStartingPrice()
        ];
    }

    /**
     * Return the netto price
     *
     * @return float
     */
    public function getNetto()
    {
        return $this->validatePrice($this->netto);
    }

    /**
     * Return the real price, brutto or netto
     *
     * @return float
     * @todo must be implemented
     */
    public function getPrice()
    {
        $netto = $this->getNetto();
        $price = $this->validatePrice($netto);

        return $price;
    }

    /**
     * Alias for getPrice
     *
     * @return float
     */
    public function value()
    {
        return $this->getPrice();
    }

    /**
     * Return the price for the view / displaying
     *
     * @return string
     */
    public function getDisplayPrice()
    {
        $price = $this->Currency->format($this->getPrice());

        if ($this->isStartingPrice()) {
            return $this->User->getLocale()->get(
                'quiqqer/erp',
                'price.starting.from',
                ['price' => $price]
            );
        }

        return $price;
    }

    /**
     * Change the price to a starting price
     * The price display is like (ab 30€, start at 30$)
     */
    public function changeToStartingPrice()
    {
        $this->startingPrice = true;
    }

    /**
     * Add a discount to the price
     *
     * @param QUI\ERP\Discount\Discount $Discount
     * @throws QUI\Exception
     */
    public function addDiscount(Discount $Discount)
    {
        /* @var $Disc Discount */
        foreach ($this->discounts as $Disc) {
            // der gleiche discount kann nur einmal enthalten sein
            if ($Disc->getId() == $Discount->getId()) {
                return;
            }

            if ($Disc->canCombinedWith($Discount) === false) {
                throw new QUI\Exception([
                    'quiqqer/products',
                    'exception.discount.not.combinable',
                    [
                        'id1' => $Disc->getId(),
                        'id2' => $Discount->getId()
                    ]
                ]);
            }
        }

        $this->discounts[] = $Discount;
    }

    /**
     * Return the assigned discounts
     *
     * @return array [Discount, Discount, Discount]
     */
    public function getDiscounts()
    {
        return $this->discounts;
    }

    /**
     * Return the currency from the price
     *
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        return $this->Currency;
    }

    /**
     * @return bool
     */
    public function isStartingPrice()
    {
        return $this->startingPrice;
    }

    /**
     * calculation
     */

    /**
     * Validates a price value
     *
     * @param number|string $value
     * @return float|double|int|null
     */
    public static function validatePrice($value)
    {
        if (is_float($value)) {
            return round($value, 4);
        }

        $value      = (string)$value;
        $isNegative = substr($value, 0, 1) === '-';

        // value cleanup
        $value = preg_replace('#[^\d,.]#i', '', $value);

        if (trim($value) === '') {
            return null;
        }

        $negativeTurn = 1;

        if ($isNegative) {
            $negativeTurn = -1;
        }

        $decimal   = mb_strpos($value, self::$decimalSeparator);
        $thousands = mb_strpos($value, self::$thousandsSeparator);

        if ($thousands === false && $decimal === false) {
            return round(floatval($value), 4) * $negativeTurn;
        }

        if ($thousands !== false && $decimal === false) {
            if (mb_substr($value, -4, 1) === self::$thousandsSeparator) {
                $value = str_replace(self::$thousandsSeparator, '', $value);
            }
        }

        if ($thousands === false && $decimal !== false) {
            $value = str_replace(self::$decimalSeparator, '.', $value);
        }

        if ($thousands !== false && $decimal !== false) {
            $value = str_replace(self::$thousandsSeparator, '', $value);
            $value = str_replace(self::$decimalSeparator, '.', $value);
        }

        return round(floatval($value), 4) * $negativeTurn;
    }
}
