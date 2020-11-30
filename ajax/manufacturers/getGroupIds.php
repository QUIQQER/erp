<?php

use QUI\ERP\Manufacturers;

/**
 * Format a price for the admin
 *
 * @param string|int|float $value
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_getGroupIds',
    function () {
        return Manufacturers::getManufacturerGroupIds();
    },
    [],
    'Permission::checkAdminUser'
);
