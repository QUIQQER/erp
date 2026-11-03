<?php

/**
 * This file contains package_quiqqer_erp_ajax_products_getVariantChildren
 */

use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\VariantParent;

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_products_getVariantChildren',
    function ($productId) {
        $Product = Products::getProduct((int)$productId);

        if (!($Product instanceof VariantParent)) {
            return [];
        }

        $variants = $Product->getVariants();

        if (!is_array($variants)) {
            return [];
        }

        $rows = array_map(function ($Variant) {
            return $Variant->getAttributes();
        }, $variants);

        $variantIds = array_map('intval', array_column($rows, 'id'));

        QUI::getEvents()->fireEvent('quiqqerProductsVariantGridData', [
            &$rows,
            $variantIds,
            $Product,
            'erp.variant-selection'
        ]);

        return $rows;
    },
    ['productId'],
    'Permission::checkAdminUser'
);
