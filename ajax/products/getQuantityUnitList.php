<?php

/**
 * This file contains package_quiqqer_erp_ajax_products_getQuantityUnitList
 */

/**
 *
 */

use QUI\ERP\Products\Handler\Fields;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_products_getQuantityUnitList',
    function () {
        $Field   = QUI\ERP\Products\Handler\Fields::getField(Fields::FIELD_UNIT);
        $options = $Field->getOptions();

        return $options['entries'];
    },
    false,
    'Permission::checkAdminUser'
);
