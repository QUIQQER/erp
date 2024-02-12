<?php

/**
 * This file contains package_quiqqer_erp_ajax_dashboard_globalProcess_getProcess
 */

use QUI\ERP\Process;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_dashboard_globalProcess_getProcess',
    function ($globalProcessId) {
        $Process = new Process($globalProcessId);

        return [
            'history' => $Process->getCompleteHistory()->toArray()
        ];
    },
    ['globalProcessId'],
    ['Permission::checkAdminUser']
);
