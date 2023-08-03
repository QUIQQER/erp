<?php

/**
 * This file contains package_quiqqer_erp_ajax_products_getProductEdit
 */

use QUI\ERP\Products\Controls\Products\ProductEdit;
use QUI\ERP\Products\Handler\Products;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_products_getProductEdit',
    function ($productId, $user) {
        $Product = Products::getProduct($productId);

        $Control = new ProductEdit([
            'Product' => $Product
        ]);

        $css = QUI\Control\Manager::getCSS();
        $html = $Control->create();

        return $css . $html;
    },
    ['productId', 'user'],
    'Permission::checkAdminUser'
);
