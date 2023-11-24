<?php

/**
 * This file contains QUI\ERP\Accounting\Calculations
 */

namespace QUI\ERP\Accounting;

use QUI;
use QUI\ERP\Exception;

use function is_array;

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
    protected array $attributes = [];

    /**
     * @var Article[]
     */
    protected array $articles = [];

    /**
     * @var QUI\ERP\Currency\Currency
     */
    protected QUI\ERP\Currency\Currency $Currency;

    /**
     * Calculations constructor.
     *
     * @param array $attributes - calculation array
     * @param array $articles - list of articles
     *
     * @throws Exception
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
    public function getSum(): CalculationValue
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
    public function getSubSum(): CalculationValue
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
    public function getNettoSum(): CalculationValue
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
    public function getNettoSubSum(): CalculationValue
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
    public function getVatSum(): CalculationValue
    {
        $sum = 0;
        $vat = $this->attributes['vatArray'];

        foreach ($vat as $data) {
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

    /**
     * Return the complete vat array / but as CalculationValue
     *
     * @return CalculationVatValue[]
     */
    public function getVat(): array
    {
        $result = [];

        foreach ($this->attributes['vatArray'] as $key => $value) {
            $result[] = new CalculationVatValue(
                $value['sum'],
                $value['text'],
                $key,
                $this->Currency
            );
        }

        return $result;
    }

    //endregion

    //region articles

    /**
     * @return Article[]
     */
    public function getArticles(): array
    {
        return $this->articles;
    }

    //endregion
}
