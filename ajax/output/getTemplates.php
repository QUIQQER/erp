<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_settings_templates
 */

use QUI\ERP\Accounting\Invoice\Settings;

/**
 * Returns the invoice templates
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_getTemplates',
    function ($entityType) {
        // @todo build Output class and fetch templates

        return Settings::getInstance()->getAvailableTemplates();
    },
    ['entityType'],
    'Permission::checkAdminUser'
);
