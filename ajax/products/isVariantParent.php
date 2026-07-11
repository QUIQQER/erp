<?php

/**
 * This file contains package_quiqqer_erp_ajax_products_isVariantParent
 */

use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\VariantParent;

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_products_isVariantParent',
    function ($productId) {
        $Product = Products::getProduct((int)$productId);
        return $Product instanceof VariantParent;
    },
    ['productId'],
    'Permission::checkAdminUser'
);
