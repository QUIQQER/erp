<?php

/**
 * This file contains package_quiqqer_erp_ajax_products_hasProductCustomFields
 */

use QUI\ERP\Products\Handler\Products;

/**
 *
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_products_hasProductCustomFields',
    function ($productId) {
        $Product = Products::getProduct($productId);
        $fields  = $Product->createUniqueProduct()->getCustomFields();

        return count($fields);
    },
    ['productId'],
    'Permission::checkAdminUser'
);
