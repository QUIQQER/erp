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
     * @return QUI\Countries\Country
     *
     * @todo ERP standard land als einstellung
     */
    public static function getCountry()
    {
        return QUI\Countries\Manager::get('de');
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
        try {
            $Package = QUI::getPackage('quiqqer/tax');
            $Config  = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            return QUI\ERP\Utils\User::IS_BRUTTO_USER;
        }

        $isNetto = $Config->getValue('shop', 'isNetto');

        if ($isNetto) {
            return QUI\ERP\Utils\User::IS_NETTO_USER;
        }

        return QUI\ERP\Utils\User::IS_BRUTTO_USER;
    }

    /**
     * Return the system calculation precision
     *
     * @return array|int|string
     */
    public static function getPrecision()
    {
        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config  = $Package->getConfig();

            if (!$Config) {
                return 8;
            }

            $precision = $Config->get('general', 'precision');

            if ($precision) {
                return $precision;
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return 8;
    }
}
