<?php

/**
 * This file contains QUI\ERP\Accounting\PriceFactors\FactorList
 */

namespace QUI\ERP\Accounting\PriceFactors;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use QUI;
use Traversable;

use function array_map;
use function count;
use function is_array;
use function json_encode;

/**
 * Class FactorList
 *
 * List with multiple price factors
 * This list can't be edited and is not changeable
 *
 * This is list is only a presentation layer
 */
class FactorList implements IteratorAggregate, Countable
{
    /**
     * internal list of price factors
     *
     * @var Factor[]
     */
    protected array $list = [];

    /**
     * FactorList constructor.
     *
     * @param array $data
     *
     * @throws QUI\ERP\Exception
     */
    public function __construct(array $data = [])
    {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $factorData) {
            if ($factorData instanceof Factor) {
                $this->list[] = $factorData;
                continue;
            }

            $this->list[] = new Factor($factorData);
        }
    }

    /**
     * Return the number of the price factors
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * Return the complete list as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(function ($Factor) {
            /* @var $Factor Factor */
            return $Factor->toArray();
        }, $this->list);
    }

    /**
     * Return the complete list as an array in json
     *
     * @return string
     */
    public function toJSON(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * @param \QUI\ERP\Accounting\PriceFactors\Factor $Factor
     * @return void
     */
    public function addFactor(Factor $Factor)
    {
        $this->list[] = $Factor;
    }

    /**
     * @param int $index
     * @param QUI\ERP\Accounting\PriceFactors\Factor $Factor
     * @return void
     */
    public function setFactor(int $index, QUI\ERP\Accounting\PriceFactors\Factor $Factor)
    {
        if (isset($this->list[$index])) {
            $this->list[$index] = $Factor;
        }
    }

    /**
     * @param int $index
     * @return \QUI\ERP\Accounting\PriceFactors\Factor|null
     */
    public function getFactor(int $index): ?Factor
    {
        if (isset($this->list[$index])) {
            return $this->list[$index];
        }

        return null;
    }

    //region iterator

    /**
     * Iterator helper
     *
     * @return ArrayIterator|Traversable
     */
    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->list);
    }

    //endregion
}
