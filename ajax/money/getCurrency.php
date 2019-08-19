<?php

/**
 * This file contains package_quiqqer_erp_ajax_money_getCurrency
 */

/**
 * Return the default currency data
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_money_getCurrency',
    function () {
        return QUI\ERP\Defaults::getCurrency()->toArray();
    },
    false,
    'Permission::checkAdminUser'
);
