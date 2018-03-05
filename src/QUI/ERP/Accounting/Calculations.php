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

        foreach ($needles as $key) {
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
     * @return CalculationValue
     */
    public function getSum()
    {
        return new CalculationValue(
            $this->attributes['sum'],
            $this->Currency,
            $this->defaultPrecision
        );
    }

    /**
     * Return the sub sum
     *
     * @return CalculationValue
     */
    public function getSubSum()
    {
        return new CalculationValue(
            $this->attributes['subSum'],
            $this->Currency,
            $this->defaultPrecision
        );
    }

    /**
     * Return the vat sum
     *
     * @return CalculationValue
     */
    public function getVatSum()
    {
        $sum = 0;
        $vat = $this->attributes['vatArray'];

        foreach ($vat as $pc => $data) {
            $sum = $sum + $data['sum'];
        }

        return new CalculationValue(
            $sum,
            $this->Currency,
            $this->defaultPrecision
        );
    }
}
