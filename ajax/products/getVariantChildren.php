<?php

/**
 * This file contains package_quiqqer_erp_ajax_products_getVariantChildren
 */

use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\VariantParent;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_products_getVariantChildren',
    function ($productId) {
        $Product = Products::getProduct((int)$productId);

        if (!($Product instanceof VariantParent)) {
            return [];
        }

        return array_map(function ($Variant) {
            return $Variant->getAttributes();
        }, $Product->getVariants());
    },
    ['productId'],
    'Permission::checkAdminUser'
);
