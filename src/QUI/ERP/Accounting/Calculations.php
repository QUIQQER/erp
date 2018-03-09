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
     * @var Article[]
     */
    protected $articles = [];

    /**
     * @var QUI\ERP\Currency\Currency
     */
    protected $Currency;

    /**
     * Calculations constructor.
     *
     * @param array $attributes - calculation array
     * @param array $articles - list of articles
     *
     * @throws \QUI\ERP\Exception
     */
    public function __construct($attributes, $articles = [])
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

        if (is_array($articles)) {
            foreach ($articles as $Article) {
                if ($Article instanceof Article) {
                    $this->articles[] = $Article;
                }
            }
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
            QUI\ERP\Defaults::getPrecision()
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
            QUI\ERP\Defaults::getPrecision()
        );
    }

    /**
     * Return the netto sum
     *
     * @return CalculationValue
     */
    public function getNettoSum()
    {
        return new CalculationValue(
            $this->attributes['nettoSum'],
            $this->Currency,
            QUI\ERP\Defaults::getPrecision()
        );
    }

    /**
     * Return the netto sub sum
     *
     * @return CalculationValue
     */
    public function getNettoSubSum()
    {
        return new CalculationValue(
            $this->attributes['nettoSubSum'],
            $this->Currency,
            QUI\ERP\Defaults::getPrecision()
        );
    }

    //region vat

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
            QUI\ERP\Defaults::getPrecision()
        );
    }

    /**
     * Return the complete vat array (vat list)
     *
     * @return mixed
     */
    public function getVatArray()
    {
        return $this->attributes['vatArray'];
    }

    //endregion

    //region articles

    /**
     * @return Article[]
     */
    public function getArticles()
    {
        return $this->articles;
    }

    //endregion
}
