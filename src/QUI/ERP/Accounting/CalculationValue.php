<?php

/**
 * This file contains QUI\ERP\Accounting\Calculations
 */

namespace QUI\ERP\Accounting;

use QUI;

/**
 * Class CalculationValue
 * - represent a number for the calculations
 *
 * @package QUI\ERP\Accounting
 */
class CalculationValue
{
    /**
     * @var QUI\ERP\Currency\Currency
     */
    protected $Currency;

    /**
     * @var int|string
     */
    protected $number = 0;

    /**
     * @var int|string
     */
    protected $precision = 8;

    /**
     * CalculationValue constructor.
     *
     * @param $number
     * @param QUI\ERP\Currency\Currency $Currency
     * @param int|bool $precision - The optional number of decimal digits to round to.
     */
    public function __construct($number, $Currency = null, $precision = false)
    {
        if (!is_numeric($number)) {
            return;
        }

        $this->number = $number;


        // precision
        if (is_numeric($precision)) {
            $this->precision = $precision;
        }


        // currency
        if ($Currency instanceof QUI\ERP\Currency\Currency) {
            $this->Currency = $Currency;

            return;
        }

        $this->Currency = QUI\ERP\Defaults::getCurrency();
    }

    /**
     * Return the CalculationValue with the wanted precision
     *
     * @param bool $precision
     * @return CalculationValue
     */
    public function precision($precision = false)
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
     * @return string
     */
    public function formatted()
    {
        return $this->Currency->format($this->number);
    }

    /**
     * Return the un-formatted number
     *
     * @return int|string
     */
    public function get()
    {
        return $this->number;
    }
}
