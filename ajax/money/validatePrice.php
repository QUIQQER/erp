<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_validatePrice
 */

/**
 * Validate the price and return a validated price
 *
 * @param string|int|float $value
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_money_validatePrice',
    function ($value) {
        return \QUI\ERP\Money\Price::validatePrice($value);
    },
    array('value'),
    'Permission::checkAdminUser'
);
