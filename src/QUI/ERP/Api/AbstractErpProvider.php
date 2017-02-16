<?php

/**
 * This file contains QUI\ERP\Api\AbstractErpProvider
 */
namespace QUI\ERP\Api;

use QUI;

/**
 * Class Coordinator
 *
 * @package QUI\ERP\Api
 */
abstract class AbstractErpProvider
{
    /**
     * Returns an array with menu items
     *
     * @return array
     */
    public static function getMenuItems()
    {
        return array();
    }
}
