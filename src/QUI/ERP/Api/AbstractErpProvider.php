<?php

/**
 * This file contains QUI\ERP\Api\AbstractErpProvider
 */

namespace QUI\ERP\Api;

use QUI;
use QUI\Controls\Sitemap\Map;

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
     * @param Map $Map
     */
    public static function addMenuItems(Map $Map)
    {
    }

    /**
     * @return array
     */
    public static function getNumberRanges()
    {
        return [];
    }

    //region mail text settings

    /**
     * Return the mail locale text, if available
     *
     * return [
     *      [
     *          'title'       => 'Title of theses mail texts',
     *          'description' => 'What are these mail texts for?',
     *          'subject'     => ['locale group', 'locale var'],
     *          'content'     => ['locale group', 'locale var']
     *      ]
     * ]
     *
     * @return array
     */
    public static function getMailLocale(): array
    {
        return [];
    }

    //end region
}
