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
     * @var null|string
     */
    protected static $timestampFormat = [];

    /**
     * @var null|bool
     */
    protected static $userRelatedCurrency = null;

    /**
     * @var null|string
     */
    protected static $dateFormat = [];

    /**
     * @param string $section
     * @param string $key
     * @return mixed|bool|string
     */
    public static function conf(string $section, string $key)
    {
        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config  = $Package->getConfig();

            return $Config->get($section, $key);
        } catch (QUI\Exception $Exception) {
        }

        return false;
    }

    /**
     * Return the default area for the ERP system
     *
     * @return QUI\ERP\Areas\Area
     * @throws QUI\Exception
     */
    public static function getArea(): Areas\Area
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
     * Return the default country
     *
     * @return QUI\Countries\Country
     */
    public static function getCountry(): QUI\Countries\Country
    {
        return QUI\Countries\Manager::getDefaultCountry();
    }

    /**
     * Return the default currency
     *
     * @return Currency\Currency
     */
    public static function getCurrency(): Currency\Currency
    {
        return QUI\ERP\Currency\Handler::getDefaultCurrency();
    }

    /**
     * Return the currency of the user
     *
     * @param QUI\Interfaces\Users\User $User
     * @return Currency\Currency|null
     */
    public static function getUserCurrency($User = null): ?Currency\Currency
    {
        if (self::$userRelatedCurrency !== null) {
            if (self::$userRelatedCurrency) {
                return QUI\ERP\Currency\Handler::getUserCurrency($User);
            }

            return self::getCurrency();
        }

        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config  = $Package->getConfig();

            self::$userRelatedCurrency = $Config->get('general', 'userRelatedCurrency');

            if (!self::$userRelatedCurrency) {
                return self::getCurrency();
            }
        } catch (QUI\Exception $Exception) {
        }

        return QUI\ERP\Currency\Handler::getUserCurrency($User);
    }

    /**
     * Return the global brutto netto status
     *
     * @return int
     */
    public static function getBruttoNettoStatus(): int
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

    /**
     * Return the main timestamp format
     *
     * @param false|string $lang - language of the wanted timestamp
     * @return int|null|string
     */
    public static function getTimestampFormat($lang = false)
    {
        if ($lang === false) {
            $lang = QUI::getLocale()->getCurrent();
        }

        if (!isset(self::$timestampFormat[$lang])) {
            return self::$timestampFormat[$lang];
        }

        self::$timestampFormat[$lang] = '%c';

        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config  = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return self::$timestampFormat[$lang];
        }

        $value = $Config->get('timestampFormat', $lang);

        if ($value !== false) {
            self::$timestampFormat[$lang] = $value;
        }

        return self::$timestampFormat[$lang];
    }

    /**
     * Return the main date format
     *
     * @param bool|string $lang
     * @return string
     */
    public static function getDateFormat($lang = false): string
    {
        if ($lang === false) {
            $lang = QUI::getLocale()->getCurrent();
        }

        if (isset(self::$dateFormat[$lang])) {
            return self::$dateFormat[$lang];
        }

        self::$dateFormat[$lang] = '%D';

        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config  = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return self::$dateFormat[$lang];
        }

        $value = $Config->get('dateFormat', $lang);

        if ($value !== false) {
            self::$dateFormat[$lang] = $value;
        }

        return self::$dateFormat[$lang];
    }

    /**
     * Return the ERP logo
     * - if no logo is set, the default logo of the default project will be used
     *
     * @return false|QUI\Projects\Media\Image|string
     */
    public static function getLogo()
    {
        try {
            $Config = QUI::getPackage('quiqqer/erp')->getConfig();
            $logo   = $Config->get('general', 'logo');

            if (!empty($logo)) {
                return QUI\Projects\Media\Utils::getImageByUrl($logo);
            }
        } catch (QUI\Exception $Exception) {
        }

        return QUI::getProjectManager()->getStandard()->getMedia()->getLogoImage();
    }
}
