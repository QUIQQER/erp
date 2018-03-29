<?php

/**
 * This file contains QUI\ERP\Api\NumberRangeInterface
 */

namespace QUI\ERP\Api;

use QUI;

/**
 * Class NumberRangeInterface
 *
 * @package QUI\ERP\Api
 */
interface NumberRangeInterface
{
    /**
     * Return the title of the number range
     *
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null);

    /**
     * Return the current start of the range
     *
     * @return int
     */
    public function getRange();

    /**
     * @param $range
     */
    public function setRange($range);
}
