<?php

/**
 * This file contains QUI\ERP\Accounting\PriceFactors\FactorList
 */

namespace QUI\ERP\Accounting\PriceFactors;

use QUI\ERP\Exception;
use QUI\System\Log;
use QUI\Utils\Math;

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
            'visible'
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
     * Return the sum
     *
     * @return float|int
     */
    public function getSum()
    {
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
        return $this->sum - $this->nettoSum;
    }

    /**
     * Return the vat %
     *
     * @return int
     */
    public function getVat()
    {
        $vat      = abs($this->sum - $this->nettoSum);
        $nettoSum = abs($this->nettoSum);

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
            'visible'           => $this->isVisible()
        ];
    }

    /**
     * Return the Factor as an array in json
     *
     * @return string
     */
    public function toJSON()
    {
        return json_encode($this->toArray());
    }
}
