<?php

/**
 * This file contains QUI\ERP\Utils\User
 */

namespace QUI\ERP\Utils;

use QUI;
use QUI\Interfaces\Users\User as UserInterface;

/**
 * Class User Utils
 *
 * @package QUI\ERP\Utils
 * @author www.pcsg.de (Henning Leutz)
 */
class User
{
    /**
     * netto flag
     */
    const IS_NETTO_USER = 1;

    /**
     * brutto flag
     */
    const IS_BRUTTO_USER = 2;

    /**
     * Return the brutto netto status
     * is the user a netto or brutto user
     *
     * @param UserInterface $User
     * @return bool
     */
    public static function getBruttoNettoUserStatus(UserInterface $User)
    {
        if (QUI::getUsers()->isSystemUser($User)) {
            return self::IS_NETTO_USER;
        }

        $nettoStatus = $User->getAttribute('quiqqer.erp.isNettoUser');

        if (is_numeric($nettoStatus)) {
            $nettoStatus = (int)$nettoStatus;
        }

        switch ($nettoStatus) {
            case self::IS_NETTO_USER:
            case self::IS_BRUTTO_USER:
                return $nettoStatus;
        }

        $euVatId = $User->getAttribute('quiqqer.erp.euVatId');
        $taxId   = $User->getAttribute('quiqqer.erp.taxId');

        if (!empty($euVatId) || !empty($taxId)) {
            return self::IS_NETTO_USER;
        }

        try {
            $Package = QUI::getPackage('quiqqer/tax');
        } catch (QUI\Exception $Exception) {
            return self::IS_BRUTTO_USER;
        }

        $Config = $Package->getConfig();

        try {
            $Address = self::getUserERPAddress($User);

            if (is_object($Address)
                && $Address
                && $Address->getAttribute('company')
            ) {
                if ($Config->getValue('shop', 'companyForceBruttoPrice')) {
                    return self::IS_BRUTTO_USER;
                }

                return self::IS_NETTO_USER;
            }

            if (is_array($Address)
                && isset($Address['company'])
                && $Address['company'] == 1
            ) {
                if ($Config->getValue('shop', 'companyForceBruttoPrice')) {
                    return self::IS_BRUTTO_USER;
                }

                return self::IS_NETTO_USER;
            }
        } catch (QUI\Exception $Exception) {
            // no address found
        }

        $isNetto = $Config->getValue('shop', 'isNetto');

        if ($isNetto) {
            return self::IS_NETTO_USER;
        }


        try {
            $Tax = QUI\ERP\Tax\Utils::getTaxByUser($User);

            if ($Tax->getValue() == 0) {
                return self::IS_NETTO_USER;
            }
        } catch (QUI\Exception $Exception) {
            return self::IS_NETTO_USER;
        }

        return self::IS_BRUTTO_USER;
    }

    /**
     * Is the user a netto user?
     *
     * @param UserInterface $User
     * @return bool
     */
    public static function isNettoUser(UserInterface $User)
    {
        return self::getBruttoNettoUserStatus($User) === self::IS_NETTO_USER;
    }

    /**
     * Return the area of the user
     * if user is in no area, the default one of the shop would be used
     *
     * @param UserInterface $User
     * @return bool|QUI\ERP\Areas\Area
     * @throws QUI\Exception
     */
    public static function getUserArea(UserInterface $User)
    {
        $Country = $User->getCountry();
        $Area    = QUI\ERP\Areas\Utils::getAreaByCountry($Country);

        if ($Area) {
            return $Area;
        }

        return QUI\ERP\Defaults::getArea();
    }

    /**
     * Return the user ERP address (Rechnungsaddresse, Accounting Address)
     *
     * @param UserInterface $User
     * @return false|QUI\Users\Address
     * @throws QUI\Exception
     */
    public static function getUserERPAddress(UserInterface $User)
    {
        if (!QUI::getUsers()->isUser($User)) {
            throw new QUI\Exception(array(
                'quiqqer/erp',
                'exception.no.user'
            ));
        }

        /* @var $User QUI\Users\User */
        if (!$User->getAttribute('quiqqer.erp.address')) {
            return $User->getStandardAddress();
        }

        $erpAddress = $User->getAttribute('quiqqer.erp.address');

        try {
            return $User->getAddress($erpAddress);
        } catch (QUI\Exception $Exception) {
        }

        return $User->getStandardAddress();
    }

    /**
     * Return the area of the shop
     *
     * @return QUI\ERP\Areas\Area
     * @throws QUI\Exception
     * @deprecated use QUI\ERP\Defaults::getShopArea()
     */
    public static function getShopArea()
    {
        return QUI\ERP\Defaults::getArea();
    }

    /**
     * Filter unwanted user attributes
     * Therefore we can use the attributes in the ERP stack
     *
     * @param $attributes
     * @return array
     */
    public static function filterCustomerAttributes($attributes = array())
    {
        if (!is_array($attributes)) {
            return [];
        }

        $needle = [
            'uuid',
            'id',
            'email',
            'lang',
            'usergroup',
            'username',
            'active',
            'regdate',
            'lastvisit',
            'lastedit',

            'firstname',
            'lastname',
            'usertitle',
            'company',
            'birthday',
            'avatar',

            'quiqqer.erp.euVatId',
            'quiqqer.erp.taxId',
            'quiqqer.erp.address',
            'quiqqer.erp.isNettoUser'
        ];

        $needle = array_flip($needle);
        $result = [];

        foreach ($attributes as $key => $value) {
            if (isset($needle[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
