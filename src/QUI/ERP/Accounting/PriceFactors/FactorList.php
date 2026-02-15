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
use function array_values;
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
    protected array $factorList = [];

    /**
     * FactorList constructor.
     *
     * @param array<Factor|array> $data
     *
     * @throws QUI\ERP\Exception
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $factorData) {
            if ($factorData instanceof Factor) {
                $this->factorList[] = $factorData;
                continue;
            }

            $this->factorList[] = new Factor($factorData);
        }
    }

    /**
     * Return the number of the price factors
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->factorList);
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
        }, $this->factorList);
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
     * @param Factor $Factor
     * @return void
     */
    public function addFactor(Factor $Factor): void
    {
        $this->factorList[] = $Factor;
    }

    /**
     * @param int $index
     * @param Factor $Factor
     * @return void
     */
    public function setFactor(int $index, Factor $Factor): void
    {
        if (isset($this->factorList[$index])) {
            $this->factorList[$index] = $Factor;
        }
    }

    public function removeFactor(int $index): void
    {
        if (isset($this->factorList[$index])) {
            unset($this->factorList[$index]);
            $this->factorList = array_values($this->factorList);
        }
    }

    /**
     * @param int $index
     * @return Factor|null
     */
    public function getFactor(int $index): ?Factor
    {
        if (isset($this->factorList[$index])) {
            return $this->factorList[$index];
        }

        return null;
    }

    //region iterator

    /**
     * Iterator helper
     *
     * @return Traversable
     */
    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->factorList);
    }

    //endregion
}
