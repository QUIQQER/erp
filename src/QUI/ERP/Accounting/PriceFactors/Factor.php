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
    protected string $identifier = '';
    protected string $title = '';
    protected mixed $description = '';
    protected int|float $sum = 0;
    protected mixed $sumFormatted = '';
    protected mixed $nettoSum = '';
    protected string $nettoSumFormatted = '';
    protected int $visible = 1;
    protected float|bool $vat = false;
    protected string|bool $valueText = false;
    protected mixed $value;

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

        $this->title = $data['title'];
        $this->description = $data['description'];
        $this->sum = $data['sum'];
        $this->sumFormatted = $data['sumFormatted'];
        $this->nettoSum = $data['nettoSum'];
        $this->nettoSumFormatted = $data['nettoSumFormatted'];
        $this->visible = (int)$data['visible'];
        $this->value = $data['value'];

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
     * @return string
     */
    public function getIdentifier(): string
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
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Return the sum
     *
     * @return float|int|string
     */
    public function getSum(): float|int|string
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
     * @return float|int|string
     */
    public function getNettoSum(): float|int|string
    {
        return $this->nettoSum;
    }

    /**
     * Return the sum from the vat
     *
     * @return float|int|mixed
     */
    public function getVatSum(): mixed
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
     * @return float|bool|int
     */
    public function getVat(): float|bool|int
    {
        if ($this->euVat) {
            return 0;
        }

        if ($this->vat) {
            return $this->vat;
        }

        $vat = abs($this->sum - $this->nettoSum);
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
            'identifier' => $this->identifier,
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'sum' => $this->getSum(),
            'sumFormatted' => $this->getSumFormatted(),
            'nettoSum' => $this->getNettoSum(),
            'nettoSumFormatted' => $this->getNettoSumFormatted(),
            'visible' => $this->isVisible(),
            'value' => $this->getValue(),
            'valueText' => empty($this->valueText) ? '' : $this->valueText,
            'vat' => $this->getVat(),
            'calculation' => $this->getCalculation(),
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
    public function setEuVatStatus(bool $status): void
    {
        $this->euVat = $status;
    }

    //endregion

    /**
     * @param float|int $sum
     */
    public function setSum(float|int $sum): void
    {
        $this->sum = $sum;
    }

    /**
     * @param string $sumFormatted
     */
    public function setSumFormatted(string $sumFormatted): void
    {
        $this->sumFormatted = $sumFormatted;
    }

    /**
     * @param float|int $sum
     */
    public function setNettoSum(float|int $sum): void
    {
        $this->nettoSum = $sum;
    }

    /**
     * @param string $sumFormatted
     */
    public function setNettoSumFormatted(string $sumFormatted): void
    {
        $this->nettoSumFormatted = $sumFormatted;
    }

    /**
     * @param float|int $value
     */
    public function setValue(float|int $value): void
    {
        $this->value = $value;
    }

    /**
     * @param string $valueText
     */
    public function setValueText(string $valueText): void
    {
        $this->valueText = $valueText;
    }
}
