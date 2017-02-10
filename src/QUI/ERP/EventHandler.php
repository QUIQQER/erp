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
        echo '<script src="' . URL_OPT_DIR . 'quiqqer/erp/bin/load.js"></script>';
    }

    /**
     * event: on package setup
     * - create customer group
     *
     * @param Package $Package
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() != 'quiqqer/erp') {
            return;
        }

        // create customer group
        $Config  = $Package->getConfig();
        $groupId = $Config->getValue('general', 'groupId');

        if (!empty($groupId)) {
            return;
        }

        $Root = QUI::getGroups()->firstChild();

        $Customer = $Root->createChild(
            QUI::getLocale()->get('quiqqer/erp', 'customer.group.name'),
            QUI::getUsers()->getSystemUser()
        );

        $Config->setValue('general', 'groupId', $Customer->getId());
        $Config->save();
    }
}
