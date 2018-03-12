<?php

/**
 * This file contains QUI\ERP\Accounting\PriceFactors\FactorList
 */

namespace QUI\ERP\Accounting\PriceFactors;

use QUI;

/**
 * Class FactorList
 *
 * List with multiple price factors
 * This list cant be edited and is not changeable
 *
 * This is list is only a presentation layer
 */
class FactorList implements \IteratorAggregate
{
    /**
     * internal list of price factors
     *
     * @var Factor[]
     */
    protected $list = [];

    /**
     * FactorList constructor.
     *
     * @param array $data
     *
     * @throws QUI\ERP\Exception
     */
    public function __construct($data = [])
    {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $factorData) {
            $this->list[] = new Factor($factorData);
        }
    }

    /**
     * Return the number of the price factors
     *
     * @return int
     */
    public function count()
    {
        return count($this->list);
    }

    /**
     * Return the complete list as an array
     *
     * @return array
     */
    public function toArray()
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
    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    //region iterator

    /**
     * Iterator helper
     *
     * @return \ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->list);
    }

    //endregion
}
