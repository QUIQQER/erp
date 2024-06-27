<?php

/**
 * This file contains package_quiqqer_erp_ajax_copyEntity
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_copyEntity',
    function ($uuid, $processKeepStatus, $entityPlugin) {
        $Instance = (new QUI\ERP\Processes())->getEntity($uuid, $entityPlugin);

        if (!($Instance instanceof QUI\ERP\ErpCopyInterface)) {
            throw new QUI\Exception('This entity can not be copied!');
        }

        if ($processKeepStatus === 'existing') {
            $Copy = $Instance->copy(
                null,
                $Instance->getGlobalProcessId()
            );
        } else {
            $Copy = $Instance->copy();
        }

        return $Copy->toArray();
    },
    ['uuid', 'processKeepStatus', 'entityPlugin'],
    'Permission::checkAdminUser'
);
