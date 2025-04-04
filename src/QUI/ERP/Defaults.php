<?php

/**
 * This file contains QUI\ERP\Defaults
 */

namespace QUI\ERP;

use QUI;

use function array_values;
use function implode;

/**
 * Class Defaults
 *
 * @package QUI\ERP
 */
class Defaults
{
    /**
     * @var array
     */
    protected static array $timestampFormat = [];

    /**
     * @var null|bool
     */
    protected static ?bool $userRelatedCurrency = null;

    /**
     * @var array
     */
    protected static array $dateFormat = [];

    /**
     * @param string $section
     * @param string $key
     * @return array|bool|string
     */
    public static function conf(string $section, string $key): bool | array | string
    {
        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config = $Package->getConfig();

            return $Config->get($section, $key);
        } catch (QUI\Exception) {
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
        $Areas = new QUI\ERP\Areas\Handler();
        $Package = QUI::getPackage('quiqqer/tax');
        $Config = $Package->getConfig();
        $standardArea = $Config->getValue('shop', 'area');

        try {
            $Area = $Areas->getChild($standardArea);
        } catch (QUI\Exception) {
            QUI\System\Log::addError(
                'The ecoyn default area was not found. Please check your ecoyn area settings.'
            );

            // use area from default country
            $Country = self::getCountry();
            $Area = QUI\ERP\Areas\Utils::getAreaByCountry($Country);
        }

        if ($Area instanceof QUI\ERP\Areas\Area) {
            return $Area;
        }

        throw new QUI\Exception('The ecoyn default area was not found. Please check your ecoyn area settings.');
    }

    /**
     * Return the default country
     *
     * @return QUI\Countries\Country
     * @throws QUI\Exception
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
     * @param QUI\Interfaces\Users\User|null $User
     * @return Currency\Currency|null
     */
    public static function getUserCurrency(null | QUI\Interfaces\Users\User $User = null): ?Currency\Currency
    {
        if (self::$userRelatedCurrency !== null) {
            if (self::$userRelatedCurrency) {
                return QUI\ERP\Currency\Handler::getUserCurrency($User);
            }

            return self::getCurrency();
        }

        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config = $Package->getConfig();

            self::$userRelatedCurrency = $Config->get('general', 'userRelatedCurrency');

            if (!self::$userRelatedCurrency) {
                return self::getCurrency();
            }
        } catch (QUI\Exception) {
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
            $Config = $Package->getConfig();
        } catch (QUI\Exception) {
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
     * @return int
     */
    public static function getPrecision(): int
    {
        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config = $Package->getConfig();

            if (!$Config) {
                return 8;
            }

            $precision = $Config->get('general', 'precision');

            if ($precision) {
                return (int)$precision;
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return 8;
    }

    /**
     * Return the main timestamp format
     *
     * @param bool|string $lang - language of the wanted timestamp
     * @return int|null|string
     */
    public static function getTimestampFormat(bool | string $lang = false): int | string | null
    {
        if ($lang === false) {
            $lang = QUI::getLocale()->getCurrent();
        }

        if (isset(self::$timestampFormat[$lang])) {
            return self::$timestampFormat[$lang];
        }

        self::$timestampFormat[$lang] = 'MMM dd, yyyy, hh:mm:ss';

        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return self::$timestampFormat[$lang];
        }

        $value = $Config->get('timestampFormat', $lang);

        if (!empty($value)) {
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
    public static function getDateFormat(bool | string $lang = false): string
    {
        if ($lang === false) {
            $lang = QUI::getLocale()->getCurrent();
        }

        if (isset(self::$dateFormat[$lang])) {
            return self::$dateFormat[$lang];
        }

        self::$dateFormat[$lang] = 'MMM dd, yyyy';

        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return self::$dateFormat[$lang];
        }

        $value = $Config->get('dateFormat', $lang);
        $value = trim($value);

        if (!empty($value)) {
            self::$dateFormat[$lang] = $value;
        }

        return self::$dateFormat[$lang];
    }

    /**
     * Return the ERP logo
     * - if no logo is set, the default logo of the default project will be used
     *
     * @return ?QUI\Projects\Media\Image
     * @throws QUI\Exception
     */
    public static function getLogo(): ?QUI\Projects\Media\Image
    {
        try {
            $Config = QUI::getPackage('quiqqer/erp')->getConfig();
            $logo = $Config->get('general', 'logo');

            if (!empty($logo)) {
                return QUI\Projects\Media\Utils::getImageByUrl($logo);
            }
        } catch (QUI\Exception) {
        }

        return QUI::getProjectManager()->getStandard()->getMedia()->getLogoImage();
    }

    /**
     * Return the Short Shop Address
     *
     * @return string
     */
    public static function getShortAddress(): string
    {
        // ACME gmbH - Pferdweg 12 - 42424 Pfedestadt
        $fields = [];

        $fields[] = self::conf('company', 'name');
        $fields[] = self::conf('company', 'street');
        $fields[] = self::conf('company', 'zipCode') . ' ' . self::conf('company', 'city');

        $fields = array_values($fields);

        return implode(' - ', $fields);
    }
}
