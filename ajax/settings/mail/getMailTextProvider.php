<?php

/**
 * This file contains package_quiqqer_erp_ajax_settings_mail_getMailTextProvider
 */

/**
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_settings_mail_getMailTextProvider',
    function () {
        return QUI\ERP\Api\Coordinator::getInstance()->getMailTextsList();
    },
    false,
    'Permission::checkAdminUser'
);
