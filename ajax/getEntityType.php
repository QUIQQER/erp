<?php

/**
 * This file contains package_quiqqer_erp_ajax_getEntityType
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_getEntityType',
    function ($uuid) {
        $Instance = (new QUI\ERP\Processes())->getEntity($uuid);
        return get_class($Instance);
    },
    ['uuid'],
    'Permission::checkAdminUser'
);
