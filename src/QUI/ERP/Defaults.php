<?php

/**
 * This file contains QUI\ERP\Defaults
 */

namespace QUI\ERP;

use QUI;

/**
 * Class Defaults
 *
 * @package QUI\ERP
 */
class Defaults
{
    /**
     * Return the default area for the ERP system
     *
     * @return QUI\ERP\Areas\Area
     * @throws QUI\Exception
     */
    public static function getArea()
    {
        $Areas        = new QUI\ERP\Areas\Handler();
        $Package      = QUI::getPackage('quiqqer/tax');
        $Config       = $Package->getConfig();
        $standardArea = $Config->getValue('shop', 'area');

        $Area = $Areas->getChild($standardArea);

        /* @var $Area QUI\ERP\Areas\Area */
        return $Area;
    }

    /**
     * Return the default currency
     *
     * @return Currency\Currency
     */
    public static function getCurrency()
    {
        return QUI\ERP\Currency\Handler::getDefaultCurrency();
    }
}