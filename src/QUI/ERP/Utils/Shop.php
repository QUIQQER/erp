<?php

/**
 * This file contains QUI\ERP\Utils\Shop
 */

namespace QUI\ERP\Utils;

use Exception;
use QUI;

/**
 * Class Shop
 *
 * @package QUI\ERP\Utils
 */
class Shop
{
    /**
     * @var string|null
     */
    protected static ?string $type = null;

    /**
     * Return the shop business type
     *
     * @return array|string|null
     */
    public static function getBusinessType(): array|string|null
    {
        if (self::$type !== null) {
            return self::$type;
        }

        try {
            $Config = QUI::getPackage('quiqqer/erp')->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            // default
            self::$type = 'B2C';

            return self::$type;
        }

        self::$type = $Config->get('general', 'businessType');

        switch (self::$type) {
            case 'B2C':
            case 'B2B':
            case 'B2C-B2B':
            case 'B2B-B2C':
                return self::$type;
        }

        self::$type = 'B2C';

        return self::$type;
    }

    /**
     * Is the shop a b2b shop?
     * To know if the shop is only a b2b shop, please use isOnlyB2B()
     *
     * @return bool
     */
    public static function isB2B(): bool
    {
        return str_contains(self::getBusinessType(), 'B2B');
    }

    /**
     * Is the shop a b2c shop?
     * To know if the shop is only a b2c shop, please use isOnlyB2C()
     *
     * @return bool
     */
    public static function isB2C(): bool
    {
        return str_contains(self::getBusinessType(), 'B2C');
    }

    /**
     * Is the shop a b2c and b2b shop, but b2c is more important
     *
     * @return bool
     */
    public static function isB2BPrioritized(): bool
    {
        if (self::isB2B() === false) {
            return false;
        }

        return str_starts_with(self::getBusinessType(), 'B2B');
    }

    /**
     * Is the shop a b2c and b2b shop, but b2c is more important
     *
     * @return bool
     */
    public static function isB2CPrioritized(): bool
    {
        if (self::isB2C() === false) {
            return false;
        }

        return str_starts_with(self::getBusinessType(), 'B2C');
    }

    /**
     * Is the shop only b2b?
     *
     * @return bool
     */
    public static function isOnlyB2B(): bool
    {
        return self::getBusinessType() === 'B2B';
    }

    /**
     * Is the shop only b2c?
     *
     * @return bool
     */
    public static function isOnlyB2C(): bool
    {
        return self::getBusinessType() === 'B2C';
    }

    /**
     * Is the shipping module installed?
     *
     * @return bool
     */
    public static function isShippingInstalled(): bool
    {
        try {
            QUI::getPackageManager()->getInstalledPackage('quiqqer/shipping');
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
