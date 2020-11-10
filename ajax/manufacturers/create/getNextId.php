<?php

use QUI\ERP\Manufacturers;

/**
 * Format a price for the admin
 *
 * @param string|int|float $value
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_create_getNextId',
    function () {
        $result = QUI::getDataBase()->fetch([
            'from'  => QUI::getUsers()->table(),
            'limit' => 1,
            'order' => 'id DESC'
        ]);

        if (!isset($result[0])) {
            return 1;
        }

        return (int)$result[0]['id'] + 1;
    },
    [],
    'Permission::checkAdminUser'
);
