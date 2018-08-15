<?php

/**
 * This file contains QUI\ERP\Api\AbstractErpProvider
 */

namespace QUI\ERP\Api;

use QUI;

/**
 * Class AbstractErpProvider
 *
 * @package QUI\ERP\Api
 */
abstract class AbstractErpProvider
{
    /**
     * Add menu items to the e-commerce panel
     *
     * @param \QUI\Controls\Sitemap\Map $Map
     */
    public static function addMenuItems(QUI\Controls\Sitemap\Map $Map)
    {
    }

    /**
     * @return array
     */
    public static function getNumberRanges()
    {
        return [];
    }
}
