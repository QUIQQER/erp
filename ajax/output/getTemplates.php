<?php

use QUI\ERP\Output\Output as ERPOutput;
use QUI\Utils\Security\Orthos;

/**
 * Returns the invoice templates
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_getTemplates',
    function ($entityType) {
        return ERPOutput::getTemplates(Orthos::clear($entityType));
    },
    ['entityType'],
    'Permission::checkAdminUser'
);
