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
