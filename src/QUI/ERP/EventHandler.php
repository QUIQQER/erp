<?php

/**
 * This file contains QUI\ERP\EventHandler
 */

namespace QUI\ERP;

use QUI;
use QUI\Package\Package;

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
        if ($Package->getName() != 'quiqqer/erp') {
            return;
        }
    }

    /**
     * event: on user save
     * @todo prüfung auch für steuernummer
     *
     * @param QUI\Interfaces\Users\User $User
     * @throws QUI\ERP\Tax\Exception
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
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // netto brutto user status
        $User->setAttribute('quiqqer.erp.isNettoUser', false); // reset status

        $User->setAttribute(
            'quiqqer.erp.isNettoUser',
            QUI\ERP\Utils\User::getBruttoNettoUserStatus($User)
        );
    }
}
