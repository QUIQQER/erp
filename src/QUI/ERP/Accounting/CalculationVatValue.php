<?php

/**
 * This file contains QUI\ERP\Accounting\CalculationVatValue
 */

namespace QUI\ERP\Accounting;

use QUI;

/**
 * Class CalculationVatValue
 * - represent a number for the calculations
 * - additional represent a vat number
 *
 * @package QUI\ERP\Accounting
 */
class CalculationVatValue extends CalculationValue
{
    /**
     * @var string
     */
    protected $text = '';

    /**
     * @var int
     */
    protected $vat;

    /**
     * CalculationValue constructor.
     *
     * @param int|float|double $number
     * @param string $text
     * @param int $vat
     * @param QUI\ERP\Currency\Currency $Currency
     * @param int|bool $precision - The optional number of decimal digits to round to.
     */
    public function __construct($number, $text, $vat, $Currency = null, $precision = false)
    {
        parent::__construct($number, $Currency, $precision);

        $this->text = $text;
        $this->vat  = $vat;
    }

    /**
     * Return the vat text
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->text;
    }

    /**
     * Return the VAT
     *
     * @return int
     */
    public function getVat()
    {
        return $this->vat;
    }
}
