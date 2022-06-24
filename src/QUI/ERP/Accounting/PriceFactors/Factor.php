<?php

/**
 * This file contains QUI\ERP\Accounting\PriceFactors\Factor
 */

namespace QUI\ERP\Accounting\PriceFactors;

use QUI\ERP\Accounting\Calc as ERPCalc;
use QUI\ERP\Exception;
use QUI\Utils\Math;

use function abs;
use function floatval;
use function is_string;
use function json_encode;

/**
 * Class FactorList
 */
class Factor
{
    /**
     * @var mixed|string
     */
    protected $identifier = '';

    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @var int|double
     */
    protected $sum = 0;

    /**
     * @var string
     */
    protected $sumFormatted = '';

    /**
     * @var int|double
     */
    protected $nettoSum = '';

    /**
     * @var string
     */
    protected $nettoSumFormatted = '';

    /**
     * @var int
     */
    protected int $visible = 1;

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
    protected int $calculation = ERPCalc::CALCULATION_COMPLEMENT;

    /**
     * @var integer
     */
    protected int $calculation_basis = ERPCalc::CALCULATION_BASIS_NETTO;

    /**
     * @var bool
     */
    protected bool $euVat = false;

    /**
     * FactorList constructor.
     *
     * @param array $data
     *
     * @throws Exception
     */
    public function __construct(array $data = [])
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
                    'Missing QUI\ERP\Accounting\PriceFactors\Factor field ' . $field,
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
            $this->vat = floatval($data['vat']);
        }

        if (isset($data['valueText']) && is_string($data['valueText'])) {
            $this->valueText = $data['valueText'];
        }

        if (isset($data['identifier']) && is_string($data['identifier'])) {
            $this->identifier = $data['identifier'];
        }
    }

    /**
     * @return mixed|string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Return the title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Return the description
     *
     * @return string
     */
    public function getDescription(): string
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
    public function getSumFormatted(): string
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
     * @return float
     */
    public function getVat()
    {
        if ($this->euVat) {
            return 0;
        }

        if ($this->vat) {
            return $this->vat;
        }

        $vat      = abs($this->sum - $this->nettoSum);
        $nettoSum = abs($this->nettoSum);

        return Math::percent($vat, $nettoSum);
    }

    /**
     * Return the netto sum formatted
     *
     * @return string
     */
    public function getNettoSumFormatted(): string
    {
        return $this->nettoSumFormatted;
    }

    /**
     * @return int
     */
    public function getCalculation(): int
    {
        return $this->calculation;
    }

    /**
     * @return int
     */
    public function getCalculationBasis(): int
    {
        return $this->calculation_basis;
    }

    /**
     * @return int
     */
    public function isVisible(): int
    {
        return $this->visible;
    }

    /**
     * Return the Factor as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'identifier'        => $this->identifier,
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
    public function toJSON(): string
    {
        return json_encode($this->toArray());
    }

    //region eu vat

    /**
     * @return bool
     */
    public function isEuVat(): bool
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

    /**
     * @param int|float $sum
     */
    public function setSum($sum)
    {
        $this->sum = $sum;
    }

    /**
     * @param string $sumFormatted
     */
    public function setSumFormatted(string $sumFormatted)
    {
        $this->sumFormatted = $sumFormatted;
    }

    /**
     * @param int|float $sum
     */
    public function setNettoSum($sum)
    {
        $this->nettoSum = $sum;
    }

    /**
     * @param string $sumFormatted
     */
    public function setNettoSumFormatted(string $sumFormatted)
    {
        $this->nettoSumFormatted = $sumFormatted;
    }

    /**
     * @param int|float $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @param string $valueText
     */
    public function setValueText(string $valueText)
    {
        $this->valueText = $valueText;
    }
}
