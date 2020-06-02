<?php

/**
 * This file contains QUI\ERP\Accounting\PriceFactors\Factor
 */

namespace QUI\ERP\Accounting\PriceFactors;

use QUI\ERP\Exception;
use QUI\Utils\Math;
use QUI\ERP\Accounting\Calc as ERPCalc;

/**
 * Class FactorList
 */
class Factor
{
    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var int|double|float
     */
    protected $sum = 0;

    /**
     * @var string
     */
    protected $sumFormatted = '';

    /**
     * @var int|double|float
     */
    protected $nettoSum = '';

    /**
     * @var string
     */
    protected $nettoSumFormatted = '';

    /**
     * @var int
     */
    protected $visible = 1;

    /**
     * @var bool
     */
    protected $vat = false;

    /**
     * @var bool
     */
    protected $valueText = false;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var integer
     */
    protected $calculation = ERPCalc::CALCULATION_COMPLEMENT;

    /**
     * @var integer
     */
    protected $calculation_basis = ERPCalc::CALCULATION_BASIS_NETTO;

    /**
     * @var
     */
    protected $euVat = false;

    /**
     * FactorList constructor.
     *
     * @param array $data
     *
     * @throws Exception
     */
    public function __construct($data = [])
    {
        $fields = [
            'title',
            'description',
            'sum',
            'sumFormatted',
            'nettoSum',
            'nettoSumFormatted',
            'visible',
            'value'
        ];

        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                throw new Exception(
                    'Missing QUI\ERP\Accounting\PriceFactors\Factor field '.$field,
                    500,
                    [
                        'data' => $data
                    ]
                );
            }
        }

        $this->title             = $data['title'];
        $this->description       = $data['description'];
        $this->sum               = $data['sum'];
        $this->sumFormatted      = $data['sumFormatted'];
        $this->nettoSum          = $data['nettoSum'];
        $this->nettoSumFormatted = $data['nettoSumFormatted'];
        $this->visible           = (int)$data['visible'];
        $this->value             = $data['value'];

        if (isset($data['calculation'])) {
            $this->calculation = (int)$data['calculation'];
        }

        if (isset($data['calculation_basis'])) {
            $this->calculation_basis = (int)$data['calculation_basis'];
        }

        if (isset($data['vat'])) {
            $this->vat = (int)$data['vat'];
        }

        if (isset($data['valueText']) && \is_string($data['valueText'])) {
            $this->valueText = $data['valueText'];
        }
    }

    /**
     * Return the title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Return the description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Return the sum
     *
     * @return float|int
     */
    public function getSum()
    {
        if ($this->euVat) {
            return $this->getNettoSum();
        }

        return $this->sum;
    }

    /**
     * Return the sum
     *
     * @return string
     */
    public function getSumFormatted()
    {
        return $this->sumFormatted;
    }

    /**
     * Return the netto sum
     *
     * @return float|int
     */
    public function getNettoSum()
    {
        return $this->nettoSum;
    }

    /**
     * Return the sum from the vat
     *
     * @return float|int|mixed
     */
    public function getVatSum()
    {
        if ($this->euVat) {
            return 0;
        }

        if ($this->vat) {
            return $this->nettoSum * ($this->vat / 100);
        }

        return $this->sum - $this->nettoSum;
    }

    /**
     * Return the vat %
     *
     * @return int
     */
    public function getVat()
    {
        if ($this->euVat) {
            return 0;
        }

        if ($this->vat) {
            return $this->vat;
        }

        $vat      = \abs($this->sum - $this->nettoSum);
        $nettoSum = \abs($this->nettoSum);

        return Math::percent($vat, $nettoSum);
    }

    /**
     * Return the netto sum formatted
     *
     * @return string
     */
    public function getNettoSumFormatted()
    {
        return $this->nettoSumFormatted;
    }

    /**
     * @return int
     */
    public function getCalculation()
    {
        return $this->calculation;
    }

    /**
     * @return int
     */
    public function getCalculationBasis()
    {
        return $this->calculation_basis;
    }

    /**
     * @return int
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * Return the Factor as an array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'title'             => $this->getTitle(),
            'description'       => $this->getDescription(),
            'sum'               => $this->getSum(),
            'sumFormatted'      => $this->getSumFormatted(),
            'nettoSum'          => $this->getNettoSum(),
            'nettoSumFormatted' => $this->getNettoSumFormatted(),
            'visible'           => $this->isVisible(),
            'value'             => $this->getValue(),
            'valueText'         => empty($this->valueText) ? '' : $this->valueText,
            'vat'               => $this->getVat(),
            'calculation'       => $this->getCalculation(),
            'calculation_basis' => $this->getCalculationBasis()
        ];
    }

    /**
     * Return the Factor as an array in json
     *
     * @return string
     */
    public function toJSON()
    {
        return \json_encode($this->toArray());
    }

    //region eu vat

    public function isEuVat()
    {
        return $this->euVat;
    }

    /**
     * @param bool $status
     */
    public function setEuVatStatus(bool $status)
    {
        $this->euVat = $status;
    }

    //endregion

    public function setSum($sum)
    {
        $this->sum = $sum;
    }

    public function setSumFormatted($sumFormatted)
    {
        $this->nettoSumFormatted = $sumFormatted;
    }

    public function setNettoSum($sum)
    {
        $this->nettoSum = $sum;
    }

    public function setNettoSumFormatted($sumFormatted)
    {
        $this->nettoSumFormatted = $sumFormatted;
    }
}
