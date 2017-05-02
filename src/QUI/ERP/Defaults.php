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

    /**
     * Return the global brutto netto status
     *
     * @return int
     */
    public static function getBruttoNettoStatus()
    {
        $Package = QUI::getPackage('quiqqer/tax');
        $Config  = $Package->getConfig();
        $isNetto = $Config->getValue('shop', 'isNetto');

        if ($isNetto) {
            return QUI\ERP\Utils\User::IS_NETTO_USER;
        }

        return QUI\ERP\Utils\User::IS_BRUTTO_USER;
    }
}
