<?php

/**
 * This file contains QUI\ERP\Money\Price
 */

namespace QUI\ERP\Money;

use QUI;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Discount\Discount;
use QUI\Interfaces\Users\User;

use function floatval;
use function is_float;
use function is_int;
use function mb_strpos;
use function mb_substr;
use function preg_replace;
use function round;
use function str_replace;
use function trim;

/**
 * Class Price
 * @package QUI\ERP\Products\Price
 */
class Price
{
    /**
     * Netto Price
     * @var float|int
     */
    protected float | int $price;

    /**
     * Price currency
     * @var QUI\ERP\Currency\Currency
     */
    protected QUI\ERP\Currency\Currency $Currency;

    /**
     * Flag for Price from
     * @var bool
     */
    protected bool $isMinimalPrice = false;

    /**
     * @var array
     */
    protected array $discounts;

    /**
     * User
     * @var ?QUI\Interfaces\Users\User
     */
    protected ?QUI\Interfaces\Users\User $User = null;

    /**
     * Price constructor.
     *
     * @param float|int|null $price
     * @param Currency $Currency
     * @param User|null $User - optional, if no user, session user are used
     */
    public function __construct(
        float | int | null $price,
        QUI\ERP\Currency\Currency $Currency,
        null | QUI\Interfaces\Users\User $User = null
    ) {
        if (!$price) {
            $price = 0;
        }

        $this->price = $price;
        $this->Currency = $Currency;

        $this->User = $User;
        $this->discounts = [];

        if (!QUI::getUsers()->isUser($User)) {
            $this->User = QUI::getUserBySession();
        }
    }

    /**
     * Return the price as array notation
     * @return array
     */
    public function toArray(): array
    {
        return [
            'price' => $this->value(),
            'currency' => $this->getCurrency()->getCode(),
            'display' => $this->getDisplayPrice(),
            'isMinimalPrice' => $this->isMinimalPrice()
        ];
    }

    /**
     * Return the real price
     *
     * @return float|int|null
     */
    public function getPrice(): float | int | null
    {
        return $this->validatePrice($this->price);
    }

    /**
     * Alias for getPrice
     *
     * @return float|int|null
     */
    public function value(): float | int | null
    {
        return $this->getPrice();
    }

    /**
     * Alias for getPrice
     *
     * @return float|int|null
     */
    public function getValue(): float | int | null
    {
        return $this->getPrice();
    }

    /**
     * Return the price for the view / displaying
     *
     * @return string
     */
    public function getDisplayPrice(): string
    {
        return $this->Currency->format($this->getPrice());
    }

    /**
     * Add a discount to the price
     *
     * @param QUI\ERP\Discount\Discount $Discount
     * @throws QUI\Exception
     */
    public function addDiscount(Discount $Discount): void
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
    public function getDiscounts(): array
    {
        return $this->discounts;
    }

    /**
     * Return the currency from the price
     *
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency(): QUI\ERP\Currency\Currency
    {
        return $this->Currency;
    }

    /**
     * calculation
     */

    /**
     * Validates a price value
     *
     * @param mixed $value
     * @param QUI\Locale|null $Locale - based locale, in which the price is
     * @return float|int|null
     */
    public static function validatePrice(
        mixed $value,
        null | QUI\Locale $Locale = null
    ): float | int | null {
        if (is_float($value) || is_int($value)) {
            return round($value, QUI\ERP\Defaults::getPrecision());
        }

        if ($value instanceof Price) {
            $value = $value->getPrice();
        }

        $value = (string)$value;
        $isNegative = str_starts_with($value, '-');

        // value cleanup
        $value = preg_replace('#[^\d,.]#i', '', $value);

        if (trim($value) === '') {
            return null;
        }

        if ($Locale === null) {
            $Locale = QUI::getSystemLocale();
        }

        $negativeTurn = 1;

        if ($isNegative) {
            $negativeTurn = -1;
        }

        $decimalSeparator = $Locale->getDecimalSeparator();
        $thousandSeparator = $Locale->getGroupingSeparator();

        $decimal = mb_strpos($value, $decimalSeparator);
        $thousands = mb_strpos($value, $thousandSeparator);

        if ($thousands === false && $decimal === false) {
            return round(floatval($value), 4) * $negativeTurn;
        }

        if ($thousands !== false && $decimal === false) {
            if (mb_substr($value, -4, 1) === $thousandSeparator) {
                $value = str_replace($thousandSeparator, '', $value);
            }
        }

        if ($thousands === false && $decimal !== false) {
            $value = str_replace($decimalSeparator, '.', $value);
        }

        if ($thousands !== false && $decimal !== false) {
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        }

        $value = floatval($value);
        $value = round($value, QUI\ERP\Defaults::getPrecision());

        return $value * $negativeTurn;
    }

    /**
     * Return if the price is minimal price and higher prices exists
     *
     * @return bool
     */
    public function isMinimalPrice(): bool
    {
        return $this->isMinimalPrice;
    }

    /**
     * enables the minimal price
     * -> price from
     * -> ab
     */
    public function enableMinimalPrice(): void
    {
        $this->isMinimalPrice = true;
    }

    /**
     * enables the minimal price
     * -> price from
     * -> ab
     */
    public function disableMinimalPrice(): void
    {
        $this->isMinimalPrice = false;
    }
}
