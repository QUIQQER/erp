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
    protected $price;

    /**
     * Price currency
     * @var QUI\ERP\Currency\Currency
     */
    protected $Currency;

    /**
     * Flag for Price from
     * @var bool
     */
    protected $isMinimalPrice = false;

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
     * Price constructor.
     *
     * @param float|int|double|string $price
     * @param QUI\ERP\Currency\Currency $Currency
     * @param QUI\Users\User|boolean $User - optional, if no user, session user are used
     */
    public function __construct($price, QUI\ERP\Currency\Currency $Currency, $User = false)
    {
        $this->price    = $price;
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
            'price'          => $this->value(),
            'currency'       => $this->getCurrency()->getCode(),
            'display'        => $this->getDisplayPrice(),
            'isMinimalPrice' => $this->isMinimalPrice()
        ];
    }

    /**
     * Return the real price
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->validatePrice($this->price);
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
     * Alias for getPrice
     *
     * @return float
     */
    public function getValue()
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
        return $this->Currency->format($this->getPrice());
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
        if (\is_float($value)) {
            return \round($value, 4);
        }

        $value      = (string)$value;
        $isNegative = \substr($value, 0, 1) === '-';

        // value cleanup
        $value = \preg_replace('#[^\d,.]#i', '', $value);

        if (\trim($value) === '') {
            return null;
        }

        $negativeTurn = 1;

        if ($isNegative) {
            $negativeTurn = -1;
        }

        $decimalSeparator  = QUI::getSystemLocale()->getDecimalSeparator();
        $thousandSeparator = QUI::getSystemLocale()->getGroupingSeparator();

        $decimal   = \mb_strpos($value, $decimalSeparator);
        $thousands = \mb_strpos($value, $thousandSeparator);

        if ($thousands === false && $decimal === false) {
            return \round(\floatval($value), 4) * $negativeTurn;
        }

        if ($thousands !== false && $decimal === false) {
            if (\mb_substr($value, -4, 1) === $thousandSeparator) {
                $value = \str_replace($thousandSeparator, '', $value);
            }
        }

        if ($thousands === false && $decimal !== false) {
            $value = \str_replace($decimalSeparator, '.', $value);
        }

        if ($thousands !== false && $decimal !== false) {
            $value = \str_replace($thousandSeparator, '', $value);
            $value = \str_replace($decimalSeparator, '.', $value);
        }

        return \round(\floatval($value), 4) * $negativeTurn;
    }

    /**
     * Return if the the price is minimal price and higher prices exists
     *
     * @return bool
     */
    public function isMinimalPrice()
    {
        return $this->isMinimalPrice;
    }

    /**
     * enables the minimal price
     * -> price from
     * -> ab
     */
    public function enableMinimalPrice()
    {
        $this->isMinimalPrice = true;
    }

    /**
     * enables the minimal price
     * -> price from
     * -> ab
     */
    public function disableMinimalPrice()
    {
        $this->isMinimalPrice = false;
    }
}
