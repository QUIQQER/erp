<?php

use QUI\ERP\BankAccounts\Handler;

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_settings_bankAccounts_save',
    static function ($id, $data): array {
        $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new QUI\Exception('Invalid bank account data.');
        }

        if ($id === '' || $id === null) {
            Handler::addBankAccount($data);
        } else {
            $id = filter_var($id, FILTER_VALIDATE_INT);

            if ($id === false || $id < 0) {
                throw new QUI\Exception('Invalid bank account ID.');
            }

            Handler::updateBankAccount($id, $data);
        }

        return Handler::getList();
    },
    ['id', 'data'],
    ['Permission::checkAdminUser', 'quiqqer.settings']
);
