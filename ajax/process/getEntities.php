<?php

/**
 * This file contains package_quiqqer_erp_ajax_dashboard_process_getEntities
 */

use QUI\ERP\Process;
use QUI\ERP\Processes;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_process_getEntities',
    function ($globalProcessId, $entityHash) {
        if (!empty($entityHash)) {
            $Processes = new Processes();
            $Entity = $Processes->getEntity($entityHash);
            $Process = new Process($Entity->getGlobalProcessId());
        } else {
            $Process = new Process($globalProcessId);
        }

        return array_map(function ($Entity) {
            return $Entity->toArray();
        }, $Process->getEntities());
    },
    ['globalProcessId', 'entityHash'],
    ['Permission::checkAdminUser']
);
