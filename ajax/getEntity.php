<?php

/**
 * This file contains package_quiqqer_erp_ajax_getEntity
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_getEntity',
    function ($uuid) {
        $Instance = (new QUI\ERP\Processes())->getEntity($uuid);

        return $Instance->toArray();
    },
    ['uuid'],
    'Permission::checkAdminUser'
);
