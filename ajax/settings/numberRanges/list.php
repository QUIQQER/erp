<?php

/**
 * This file contains package_quiqqer_erp_ajax_settings_numberRanges_list
 */

use QUI\ERP\Api\Coordinator;

/**
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_settings_numberRanges_list',
    function () {
        return \array_map(function ($Range) {
            /* @var $Range QUI\ERP\Api\NumberRangeInterface */
            return [
                'title' => $Range->getTitle(),
                'range' => $Range->getRange(),
                'class' => \get_class($Range)
            ];
        }, Coordinator::getInstance()->getNumberRanges());
    },
    false,
    'Permission::checkAdminUser'
);
