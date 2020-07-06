<?php

use QUI\ERP\Output\Output as ERPOutput;
use QUI\Utils\Security\Orthos;

/**
 * Returns basic entity data used in OutputDialog
 *
 * @param string|int $entityId
 * @param string $entityType
 * @return array|false - Entity data or false
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_getEntityData',
    function ($entityId, $entityType) {
        $OutputProvider = ERPOutput::getOutputProviderByEntityType(Orthos::clear($entityType));

        if (empty($OutputProvider)) {
            return false;
        }

        return [
            'email' => $OutputProvider::getEmailAddress(Orthos::clear($entityId))
        ];
    },
    ['entityId', 'entityType'],
    'Permission::checkAdminUser'
);
