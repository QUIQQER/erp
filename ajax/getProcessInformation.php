<?php

/**
 * This file contains package_quiqqer_erp_ajax_getProcessInformation
 */

/**
 * Return all data from a process
 *
 * @param string|int|float $value
 * @return int
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_getProcessInformation',
    function ($hash) {
        return QUI\ERP\Utils\Process::getProcessInformation($hash);
    },
    ['hash'],
    'Permission::checkAdminUser'
);
