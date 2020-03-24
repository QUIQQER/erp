<?php

/**
 * This file contains QUI\ERP\EventHandler
 */

namespace QUI\ERP;

use QUI;
use QUI\Package\Package;
use Quiqqer\Engine\Collector;

/**
 * Class EventHandler
 *
 * @package QUI\ERP
 */
class EventHandler
{
    /**
     * event : on admin load footer
     */
    public static function onAdminLoadFooter()
    {
        echo '<script src="'.URL_OPT_DIR.'quiqqer/erp/bin/load.js"></script>';
    }

    /**
     * event: on package setup
     *
     * @param Package $Package
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() !== 'quiqqer/erp') {
            return;
        }
    }

    public static function onPackageConfigSave(QUI\Package\Package $Package, array $params)
    {
        if ($Package->getName() !== 'quiqqer/erp') {
            return;
        }

        $languages = QUI::availableLanguages();
        $languages = \array_flip($languages);

        try {
            $Config = $Package->getConfig();

            // timestampFormat
            if (isset($params['timestampFormat'])) {
                foreach ($params['timestampFormat'] as $language => $format) {
                    if (isset($languages[$language])) {
                        $Config->setValue('timestampFormat', $language, $format);
                    }
                }
            }

            // dateFormat
            if (isset($params['dateFormat'])) {
                foreach ($params['dateFormat'] as $language => $format) {
                    if (isset($languages[$language])) {
                        $Config->setValue('dateFormat', $language, $format);
                    }
                }
            }

            $Config->save();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * event: on user save
     * @param QUI\Interfaces\Users\User $User
     * @todo prüfung auch für steuernummer
     *
     */
    public static function onUserSave(QUI\Interfaces\Users\User $User)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        // eu vat id validation
        try {
            $Package  = QUI::getPackage('quiqqer/tax');
            $validate = $Package->getConfig()->getValue('shop', 'validateVatId');
            $vatId    = $User->getAttribute('quiqqer.erp.euVatId');

            if ($validate && $vatId && !empty($vatId)) {
                try {
                    $vatId = QUI\ERP\Tax\Utils::validateVatId($vatId);
                } catch (QUI\ERP\Tax\Exception $Exception) {
                    if ($Exception->getCode() !== 503) {
                        throw $Exception;
                    }

                    $vatId = QUI\ERP\Tax\Utils::cleanupVatId($vatId);
                }
            } elseif ($vatId) {
                $vatId = QUI\ERP\Tax\Utils::cleanupVatId($vatId);
            }

            $User->setAttribute('quiqqer.erp.euVatId', $vatId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // netto brutto user status
        // @todo im admin muss dieser schritt seperat gemacht werden
        // @todo im admin muss festgelegt werden was der nutzer ist
        // @todo das muss in das customer modul rein
//        $User->setAttribute('quiqqer.erp.isNettoUser', false); // reset status
//
//        $User->setAttribute(
//            'quiqqer.erp.isNettoUser',
//            QUI\ERP\Utils\User::getBruttoNettoUserStatus($User)
//        );
    }

    /**
     * event: on user save
     * saves the vat number
     *
     *
     * @param QUI\Users\User $User
     * @throws QUI\Exception
     */
    public static function onUserSaveBegin(QUI\Users\User $User)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Request = QUI::getRequest()->request;
        $data    = $Request->all();

        if (empty($data)) {
            return;
        }

        if (isset($data['data'])) {
            $data = \json_decode($data['data'], true);
        }

        if (isset($data['company'])) {
            try {
                $Address = $User->getStandardAddress();
                $Address->setAttribute(
                    'company',
                    QUI\Utils\Security\Orthos::clear($data['company'])
                );
                $Address->save();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if (isset($data['vatId'])) {
            $vatId = $data['vatId'];

            if (\class_exists('QUI\ERP\Tax\Utils')
                && QUI\ERP\Tax\Utils::shouldVatIdValidationBeExecuted()
                && !empty($vatId)) {
                $vatId = QUI\ERP\Tax\Utils::validateVatId($vatId);
            }

            // save VAT ID
            $User->setAttribute('quiqqer.erp.euVatId', QUI\Utils\Security\Orthos::clear($vatId));
        }

        if (isset($data['chUID'])) {
            $User->setAttribute('quiqqer.erp.chUID', QUI\Utils\Security\Orthos::clear($data['chUID']));
        }
    }

    /**
     * event: on user address save
     *
     * @param QUI\Users\Address $Address
     * @param QUI\Users\User $User
     */
    public static function onUserAddressSave(QUI\Users\Address $Address, QUI\Users\User $User)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        $Request = QUI::getRequest()->request;
        $data    = $Request->get('data');

        if (\is_string($data)) {
            $data = \json_decode($data, true);
        }

        if (empty($data) || !\is_array($data)) {
            return;
        }

        if (isset($data['vatId'])) {
            $vatId = $data['vatId'];

            try {
                if (\class_exists('QUI\ERP\Tax\Utils')
                    && QUI\ERP\Tax\Utils::shouldVatIdValidationBeExecuted()
                    && !empty($vatId)) {
                    $vatId = QUI\ERP\Tax\Utils::validateVatId($vatId);
                }

                // save VAT ID
                $User->setAttribute('quiqqer.erp.euVatId', $vatId);
                $User->save();
            } catch (QUI\ERP\Tax\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }
    }

    //region user profile extension

    /**
     * @param Collector $Collector
     * @param QUI\Users\User $User
     * @param QUI\Users\Address $Address
     */
    public static function onFrontendUserCustomerBegin(Collector $Collector, $User, $Address)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        // business type
        $businessType = 'b2c';
        $company      = $Address->getAttribute('company');

        if (!empty($company)) {
            $businessType = 'b2b';
        }

        if ($User->getAttribute('quiqqer.erp.euVatId')) {
            $businessType = 'b2b';
        }

        // template data
        $Engine->assign([
            'User'         => $User,
            'Address'      => $Address,
            'businessType' => $businessType,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C'                    => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B'                    => QUI\ERP\Utils\Shop::isB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(\dirname(__FILE__).'/FrontendUsers/customerData.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param \Quiqqer\Engine\Collector $Collector
     * @param QUI\Users\User $User
     * @param QUI\Users\Address $Address
     */
    public static function onFrontendUserDataMiddle(Collector $Collector, $User, $Address)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        $Engine->assign([
            'User'    => $User,
            'Address' => $Address,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C'                    => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B'                    => QUI\ERP\Utils\Shop::isB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(\dirname(__FILE__).'/FrontendUsers/profileData.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Users\User $User
     */
    public static function onFrontendUserAddressCreateBegin(Collector $Collector, $User)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        $Engine->assign([
            'User' => $User,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C'                    => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B'                    => QUI\ERP\Utils\Shop::isB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(\dirname(__FILE__).'/FrontendUsers/createAddressBegin.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Users\User $User
     */
    public static function onFrontendUserAddressCreateEnd(Collector $Collector, $User)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        $Engine->assign([
            'User' => $User,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C'                    => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B'                    => QUI\ERP\Utils\Shop::isB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(\dirname(__FILE__).'/FrontendUsers/createAddressEnd.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param QUI\Users\User $User
     * @param QUI\Users\Address $Address
     */
    public static function onFrontendUserAddressEditBegin(Collector $Collector, $User, $Address)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        // business type
        $businessType = 'b2c';
        $company      = $Address->getAttribute('company');

        if (!empty($company)) {
            $businessType = 'b2b';
        }

        if ($User->getAttribute('quiqqer.erp.euVatId')) {
            $businessType = 'b2b';
        }

        // template data
        $Engine->assign([
            'User'         => $User,
            'Address'      => $Address,
            'businessType' => $businessType,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C'                    => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B'                    => QUI\ERP\Utils\Shop::isB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(\dirname(__FILE__).'/FrontendUsers/editAddressBegin.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Collector $Collector
     * @param $User
     * @param $Address
     */
    public static function onFrontendUserAddressEditEnd(Collector $Collector, $User, $Address)
    {
        if (!QUI::getUsers()->isUser($User)) {
            return;
        }

        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        $Engine->assign([
            'User'    => $User,
            'Address' => $Address,

            'businessTypeIsChangeable' => !(QUI\ERP\Utils\Shop::isOnlyB2C() || QUI\ERP\Utils\Shop::isOnlyB2B()),
            'isB2C'                    => QUI\ERP\Utils\Shop::isB2C(),
            'isB2B'                    => QUI\ERP\Utils\Shop::isB2B(),
        ]);

        try {
            $Collector->append(
                $Engine->fetch(\dirname(__FILE__).'/FrontendUsers/editAddressEnd.html')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    //endregion
}
