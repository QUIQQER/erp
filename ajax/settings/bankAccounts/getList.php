<?php

/**
 * Get list of bank accounts.
 *
 * @return array
 */

use QUI\ERP\BankAccounts\Handler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_settings_bankAccounts_getList',
    function () {
        return Handler::getList();
    },
    [],
    'Permission::checkAdminUser'
);
