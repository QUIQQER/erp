<?php

/**
 * This file contains QUI\ERP\Utils\User
 */

namespace QUI\ERP\Utils;

use QUI;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Users\Address;

use function array_flip;
use function class_exists;
use function is_array;
use function is_numeric;
use function is_object;

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
     * Runtime cache for user brutt/netto status
     *
     * @var array
     */
    protected static array $userBruttoNettoStatus = [];

    /**
     * Return the brutto netto status
     * is the user a netto or brutto user
     *
     * @param UserInterface $User
     * @return integer
     */
    public static function getBruttoNettoUserStatus(UserInterface $User): int
    {
        if ($User->getAttribute('RUNTIME_NETTO_BRUTTO_STATUS')) {
            return $User->getAttribute('RUNTIME_NETTO_BRUTTO_STATUS');
        }

        if ($User instanceof QUI\Users\Nobody) {
            $status = QUI::getSession()->get('quiqqer.erp.b2b.status');

            if (is_numeric($status)) {
                return (int)$status;
            }
        }

        $uid = $User->getUUID();

        if (isset(self::$userBruttoNettoStatus[$uid])) {
            return self::$userBruttoNettoStatus[$uid];
        }

        try {
            $Package = QUI::getPackage('quiqqer/erp');
            $Config = $Package->getConfig();

            if ($Config->getValue('general', 'businessType') === 'B2B') {
                self::$userBruttoNettoStatus[$uid] = QUI\ERP\Utils\User::IS_NETTO_USER;
                return self::$userBruttoNettoStatus[$uid];
            }
        } catch (QUI\Exception) {
        }


        if (QUI::getUsers()->isSystemUser($User)) {
            self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

            return self::$userBruttoNettoStatus[$uid];
        }

        if ($User instanceof QUI\ERP\User && $User->hasBruttoNettoStatus()) {
            if ($User->isNetto()) {
                self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;
            } else {
                self::$userBruttoNettoStatus[$uid] = self::IS_BRUTTO_USER;
            }

            return self::$userBruttoNettoStatus[$uid];
        }

        $nettoStatus = $User->getAttribute('quiqqer.erp.isNettoUser');

        if (is_numeric($nettoStatus)) {
            $nettoStatus = (int)$nettoStatus;
        }

        switch ($nettoStatus) {
            case self::IS_NETTO_USER:
            case self::IS_BRUTTO_USER:
                self::$userBruttoNettoStatus[$uid] = $nettoStatus;

                return self::$userBruttoNettoStatus[$uid];
        }

        $euVatId = $User->getAttribute('quiqqer.erp.euVatId');
        $taxId = $User->getAttribute('quiqqer.erp.taxId');

        if (!empty($euVatId) || !empty($taxId)) {
            self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

            return self::$userBruttoNettoStatus[$uid];
        }

        try {
            $Package = QUI::getPackage('quiqqer/tax');
        } catch (QUI\Exception) {
            self::$userBruttoNettoStatus[$uid] = self::IS_BRUTTO_USER;

            return self::$userBruttoNettoStatus[$uid];
        }

        try {
            $Config = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

            return self::$userBruttoNettoStatus[$uid];
        }

        // Verifizierung als Unternehmen einbauen
        try {
            $Address = self::getUserERPAddress($User);

            if (is_object($Address) && $Address) {
                $company = $Address->getAttribute('company');

                if (!empty($company)) {
                    if ($Config->getValue('shop', 'companyForceBruttoPrice')) {
                        self::$userBruttoNettoStatus[$uid] = self::IS_BRUTTO_USER;

                        return self::$userBruttoNettoStatus[$uid];
                    }

                    self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

                    return self::$userBruttoNettoStatus[$uid];
                }
            }

            if (
                is_array($Address)
                && isset($Address['company'])
                && $Address['company'] == 1
            ) {
                if ($Config->getValue('shop', 'companyForceBruttoPrice')) {
                    self::$userBruttoNettoStatus[$uid] = self::IS_BRUTTO_USER;

                    return self::$userBruttoNettoStatus[$uid];
                }

                self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

                return self::$userBruttoNettoStatus[$uid];
            }
        } catch (QUI\Exception) {
            // no address found
        }

        // @todo es gibt neue einstellungen b2b, b2c b2bANDb2c ... von diesen einstellungen ausgehen
        // @todo tax ist nicht optimal dafür

        $isNetto = $Config->getValue('shop', 'isNetto');

        if ($isNetto) {
            self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

            return self::$userBruttoNettoStatus[$uid];
        }


        try {
            $Tax = QUI\ERP\Tax\Utils::getTaxByUser($User);

            if ($Tax->getValue() == 0) {
                self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

                return self::$userBruttoNettoStatus[$uid];
            }
        } catch (QUI\Exception) {
            self::$userBruttoNettoStatus[$uid] = self::IS_NETTO_USER;

            return self::$userBruttoNettoStatus[$uid];
        }

        self::$userBruttoNettoStatus[$uid] = self::IS_BRUTTO_USER;

        return self::$userBruttoNettoStatus[$uid];
    }

    /**
     * Is the user a netto user?
     *
     * @param UserInterface $User
     * @return bool
     */
    public static function isNettoUser(UserInterface $User): bool
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
    public static function getUserArea(UserInterface $User): bool|QUI\ERP\Areas\Area
    {
        $CurrentAddress = $User->getAttribute('CurrentAddress');

        if ($CurrentAddress instanceof Address) {
            try {
                $Country = $CurrentAddress->getCountry();
                $Area = QUI\ERP\Areas\Utils::getAreaByCountry($Country);

                if ($Area) {
                    return $Area;
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        try {
            $addressId = $User->getAttribute('quiqqer.erp.address');
            $Address = $User->getAddress($addressId);
            $Country = $Address->getCountry();
            $Area = QUI\ERP\Areas\Utils::getAreaByCountry($Country);

            if ($Area) {
                return $Area;
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }


        $Country = $User->getCountry();
        $Area = QUI\ERP\Areas\Utils::getAreaByCountry($Country);

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
    public static function getUserERPAddress(UserInterface $User): bool|Address
    {
        if (!QUI::getUsers()->isUser($User)) {
            throw new QUI\Exception([
                'quiqqer/erp',
                'exception.no.user'
            ]);
        }

        /* @var $User QUI\Users\User */
        if (!$User->getAttribute('quiqqer.erp.address')) {
            return $User->getStandardAddress();
        }

        $erpAddress = $User->getAttribute('quiqqer.erp.address');

        try {
            return $User->getAddress($erpAddress);
        } catch (QUI\Exception) {
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
    public static function getShopArea(): QUI\ERP\Areas\Area
    {
        return QUI\ERP\Defaults::getArea();
    }

    /**
     * Filter unwanted user attributes
     * Therefore we can use the attributes in the ERP stack
     *
     * @param array $attributes
     * @return array
     */
    public static function filterCustomerAttributes(array $attributes = []): array
    {
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
            'address',

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

    /**
     * @param UserInterface $User
     * @param $Address
     */
    public static function setUserCurrentAddress(
        QUI\Interfaces\Users\User $User,
        $Address
    ) {
        if (class_exists('QUI\ERP\Tax\Utils')) {
            QUI\ERP\Tax\Utils::cleanUpUserTaxCache($User);
        }

        $User->setAttribute('CurrentAddress', $Address);
    }
}
