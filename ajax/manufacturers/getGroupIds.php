<?php

/**
 * Format a price for the admin
 *
 * @param string|int|float $value
 * @return string
 */

use QUI\ERP\Manufacturers;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_getGroupIds',
    function () {
        return Manufacturers::getManufacturerGroupIds();
    },
    [],
    'Permission::checkAdminUser'
);
