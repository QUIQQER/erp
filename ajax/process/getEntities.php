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
            $entityData = $Entity->toArray();
            $entityData['processing_status'] = [
                'id' => 0,
                'title' => '---',
                'color' => '#999999',
            ];

            if (method_exists($Entity, 'getProcessingStatus')) {
                /* @var $ProcessingStatus QUI\ERP\Accounting\Invoice\ProcessingStatus\Status */
                $ProcessingStatus = $Entity->getProcessingStatus();

                if ($ProcessingStatus) {
                    $entityData['processing_status'] = [
                        'id' => $ProcessingStatus->getId(),
                        'title' => $ProcessingStatus->getTitle(),
                        'color' => $ProcessingStatus->getColor()
                    ];
                }
            }

            return $entityData;
        }, $Process->getEntities());
    },
    ['globalProcessId', 'entityHash'],
    ['Permission::checkAdminUser']
);
