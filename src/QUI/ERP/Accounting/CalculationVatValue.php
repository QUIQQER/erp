<?php

/**
 * This file contains QUI\ERP\Accounting\CalculationVatValue
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Currency\Currency;

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
    protected string $text = '';

    /**
     * @var float
     */
    protected float $vat;

    /**
     * CalculationValue constructor.
     *
     * @param int|float $number
     * @param string $text
     * @param float|int $vat
     * @param Currency|null $Currency
     * @param bool|int $precision - The optional number of decimal digits to round to.
     */
    public function __construct(
        int | float $number,
        string $text,
        float | int $vat,
        null | QUI\ERP\Currency\Currency $Currency = null,
        bool | int $precision = false
    ) {
        parent::__construct($number, $Currency, $precision);

        $this->text = $text;
        $this->vat = $vat;
    }

    /**
     * Return the vat text
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->text;
    }

    /**
     * Return the VAT
     *
     * @return float
     */
    public function getVat(): float
    {
        return $this->vat;
    }
}
