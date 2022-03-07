<?php

use QUI\ERP\BankAccounts\Handler;

/**
 * Get list of bank accounts.
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_settings_bankAccounts_getList',
    function () {
        return Handler::getList();
    },
    [],
    'Permission::checkAdminUser'
);
