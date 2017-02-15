<?php

/**
 * This file contains package_quiqqer_bill_ajax_invoices_list
 */

/**
 * Returns invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_panel_list',
    function () {
        return \QUI\ERP\Api\Coordinator::getInstance()->getMenuItems();
    },
    false,
    'Permission::checkAdminUser'
);
