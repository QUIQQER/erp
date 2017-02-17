<?php

/**
 * This file contains package_quiqqer_erp_ajax_settings_numberRanges_set
 */

use QUI\ERP\Api\Coordinator;

/**
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_settings_numberRanges_set',
    function ($className, $newIndex) {
        $ranges = Coordinator::getInstance()->getNumberRanges();

        foreach ($ranges as $Range) {
            /* @var $Range \QUI\ERP\Api\NumberRangeInterface */
            if (get_class($Range) === $className) {
                $Range->setRange((int)$newIndex);
            }
        }
    },
    array('className', 'newIndex'),
    'Permission::checkAdminUser'
);
