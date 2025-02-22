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
    public function getTitle(null | QUI\Locale $Locale = null): string;

    /**
     * Return the current start of the range
     *
     * @return int
     */
    public function getRange(): int;

    /**
     * @param int $range
     */
    public function setRange(int $range);
}
