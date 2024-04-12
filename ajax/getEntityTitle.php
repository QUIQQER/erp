<?php

/**
 * This file contains package_quiqqer_erp_ajax_getEntityTitle
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_getEntityTitle',
    function ($uuid) {
        $Instance = (new QUI\ERP\Processes())->getEntity($uuid);
        $class = get_class($Instance);

        return QUI::getLocale()->get('quiqqer/erp', 'entity.title.' . $class);
    },
    ['uuid'],
    'Permission::checkAdminUser'
);
