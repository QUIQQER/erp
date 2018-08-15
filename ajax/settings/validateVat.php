<?php

/**
 * This file contains package_quiqqer_erp_ajax_settings_validateVat
 */

/**
 * Validate a vat number
 *
 * @param string|int|float $value
 * @return int
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_settings_validateVat',
    function ($vatId) {
        if (!class_exists('\SoapClient')) {
            return -1;
        }

        if (!class_exists('\QUI\ERP\Tax\Utils')) {
            return -1;
        }

        try {
            QUI\ERP\Tax\Utils::validateVatId($vatId);

            return 1;
        } catch (QUI\ERP\Tax\Exception $Exception) {
            return 0;
        }
    },
    ['vatId'],
    'Permission::checkAdminUser'
);
