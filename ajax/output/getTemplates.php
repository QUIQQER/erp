<?php

/**
 * Returns available output templates
 *
 * @param string $entityType (optional) - Restrict templates to those for $entityType
 * @return array
 */

use QUI\ERP\Output\Output as ERPOutput;
use QUI\Utils\Security\Orthos;

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_output_getTemplates',
    function ($entityType = null) {
        return ERPOutput::getTemplates(Orthos::clear($entityType));
    },
    ['entityType'],
    'Permission::checkAdminUser'
);
