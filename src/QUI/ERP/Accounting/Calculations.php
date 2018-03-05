<?php

/**
 * This file contains QUI\ERP\Accounting\Calculations
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Exception;

/**
 * Class Calculations
 *
 * @package QUI\ERP\Accounting
 */
class Calculations
{
    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var QUI\ERP\Currency\Currency
     */
    protected $Currency;

    /**
     * @var integer
     */
    protected $defaultPrecision = 8;

    /**
     * Calculations constructor.
     *
     * @param array $attributes - calculation array
     *
     * @throws \QUI\ERP\Exception
     */
    public function __construct($attributes)
    {
        $needles = [
            'sum',
            'subSum',
            'nettoSum',
            'nettoSubSum',
            'vatArray',
            'vatText',
            'isEuVat',
            'isNetto',
            'currencyData'
        ];

        foreach ($attributes as $key) {
            if (!isset($attributes[$key])) {
                throw new Exception('Missing Calculations attribute');
            }
        }

        $this->attributes = $attributes;

        try {
            $this->Currency = QUI\ERP\Currency\Handler::getCurrency(
                $attributes['currencyData']['code']
            );
        } catch (QUI\Exception $Exception) {
            $this->Currency = QUI\ERP\Defaults::getCurrency();
        }

        try {
            $Package   = QUI::getPackage('quiqqer/erp');
            $Config    = $Package->getConfig();
            $precision = $Config->get('general', 'precision');

            if ($precision) {
                $this->defaultPrecision = $precision;
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * Return the sum
     *
     * @param bool|integer $precision - optional, Returns the rounded value of val to specified precision (number of digits after the decimal point).
     * @return float
     */
    public function getSum($precision = false)
    {
        $p = $this->defaultPrecision;

        if ($precision !== false) {
            $p = $precision;
        }

        return round($this->attributes['sum'], $p);
    }

    /**
     * Return the sub sum
     *
     * @param bool|integer $precision - optional, Returns the rounded value of val to specified precision (number of digits after the decimal point).
     * @return float
     */
    public function getSubSum($precision = false)
    {
        $p = $this->defaultPrecision;

        if ($precision !== false) {
            $p = $precision;
        }

        return round($this->attributes['subSum'], $p);
    }

    /**
     * Return the vat sum
     *
     * @param bool|integer $precision - optional, Returns the rounded value of val to specified precision (number of digits after the decimal point).
     * @return int
     */
    public function getVatSum($precision = false)
    {
        $sum = 0;
        $vat = $this->attributes['vatArray'];

        $p = $this->defaultPrecision;

        if ($precision !== false) {
            $p = $precision;
        }

        foreach ($vat as $pc => $data) {
            $sum = $sum + $data['sum'];
        }

        return round($sum, $p);
    }
}
