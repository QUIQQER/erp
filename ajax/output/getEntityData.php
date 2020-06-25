<?php

use QUI\ERP\Output\Output as ERPOutput;

/**
 * Returns the invoice templates
 *
 * @return array|false - Entity data or false
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_getEntityData',
    function ($entityId, $package) {
        \QUI\System\Log::writeRecursive($entityId);
        \QUI\System\Log::writeRecursive($package);

        $OutputProvider = ERPOutput::getOutputProviderByPackage($package);

        if (empty($OutputProvider)) {
            return false;
        }

        return [
            'email' => $OutputProvider::getEmailAddress($entityId)
        ];
    },
    ['entityId', 'package'],
    'Permission::checkAdminUser'
);
