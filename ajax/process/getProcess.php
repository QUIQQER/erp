<?php

/**
 * This file contains package_quiqqer_erp_ajax_process_getProcess
 */

use QUI\ERP\Process;
use QUI\ERP\Processes;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_process_getProcess',
    function ($globalProcessId, $hash) {
        if (!empty($hash) && empty($globalProcessId)) {
            $Entity = (new Processes())->getEntity($hash);
            $globalProcessId = $Entity->getGlobalProcessId();
        }

        $Process = new Process($globalProcessId);

        return [
            'globalProcessId' => $Process->getUUID(),
            'history' => $Process->getCompleteHistory()->toArray()
        ];
    },
    ['globalProcessId', 'hash'],
    ['Permission::checkAdminUser']
);
