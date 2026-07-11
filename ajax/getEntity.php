<?php

/**
 * This file contains package_quiqqer_erp_ajax_getEntity
 */

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_getEntity',
    function ($uuid, $entityPlugin) {
        $Instance = (new QUI\ERP\Processes())->getEntity($uuid, $entityPlugin);

        return $Instance->toArray();
    },
    ['uuid', 'entityPlugin'],
    'Permission::checkAdminUser'
);
