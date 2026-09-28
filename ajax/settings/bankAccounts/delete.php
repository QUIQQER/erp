<?php

use QUI\ERP\BankAccounts\Handler;

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_settings_bankAccounts_delete',
    static function ($id): array {
        $id = filter_var($id, FILTER_VALIDATE_INT);

        if ($id === false || $id < 0) {
            throw new QUI\Exception('Invalid bank account ID.');
        }

        Handler::deleteBankAccount($id);

        return Handler::getList();
    },
    ['id'],
    ['Permission::checkAdminUser', 'quiqqer.settings']
);
