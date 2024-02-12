<?php

/**
 * This file contains package_quiqqer_erp_ajax_dashboard_globalProcess_getList
 */

use QUI\ERP\Processes;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_dashboard_globalProcess_getList',
    function () {
        return (new Processes())->getList();
    },
    [],
    ['Permission::checkAdminUser']
);
