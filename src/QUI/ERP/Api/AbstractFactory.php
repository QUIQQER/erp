<?php

namespace QUI\ERP\Api;

use QUI;

/**
 * Class Coordinator
 *
 * @package QUI\ERP\Api
 */
abstract class AbstractFactory
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
