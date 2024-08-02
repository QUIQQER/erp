<?php

/**
 * This file contains QUI\ERP\Accounting\CalculationValue
 */

namespace QUI\ERP\Accounting;

use QUI;

use function is_numeric;
use function round;

/**
 * Class CalculationValue
 * - represent a number for the calculations
 *
 * @package QUI\ERP\Accounting
 */
class CalculationValue
{
    protected QUI\ERP\Currency\Currency $Currency;

    protected int|float $number = 0;

    protected int|float $precision = 8;

    /**
     * CalculationValue constructor.
     *
     * @param $number
     * @param QUI\ERP\Currency\Currency|null $Currency
     * @param bool|int $precision - The optional number of decimal digits to round to.
     */
    public function __construct($number, QUI\ERP\Currency\Currency $Currency = null, bool|int $precision = false)
    {
        if (!is_numeric($number)) {
            return;
        }

        $this->number = $number;

        // precision
        if (is_numeric($precision)) {
            $this->precision = $precision;
        } else {
            $this->precision = QUI\ERP\Defaults::getPrecision();
        }

        // currency
        if ($Currency instanceof QUI\ERP\Currency\Currency) {
            $this->Currency = $Currency;

            return;
        }

        $this->Currency = QUI\ERP\Defaults::getCurrency();
    }

    /**
     * @return float|int
     */
    public function value(): float|int
    {
        return $this->number;
    }

    /**
     * Return the CalculationValue with the wanted precision
     *
     * @param bool $precision
     * @return CalculationValue
     */
    public function precision(bool $precision = false): CalculationValue
    {
        if ($precision === false) {
            return $this;
        }

        return new CalculationValue(
            $this->number,
            $this->Currency,
            $precision
        );
    }

    /**
     * Return the formatted number
     *
     * @param null|QUI\Locale $Locale - optional, Locale object for the formatting
     * @return string
     */
    public function formatted(QUI\Locale $Locale = null): string
    {
        return $this->Currency->format($this->number, $Locale);
    }

    /**
     * Return the un-formatted number
     *
     * @return float
     */
    public function get(): float
    {
        return round($this->number, $this->precision);
    }
}
