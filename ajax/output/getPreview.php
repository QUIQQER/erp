<?php

/**
 * Returns the invoice templates
 *
 * @return array
 */

use QUI\ERP\Output\Output as ERPOutput;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_getPreview',
    function ($entity, $template) {
        $entity = Orthos::clearArray(\json_decode($entity, true));
        $template = Orthos::clearArray(\json_decode($template, true));

        if (!isset($template['provider'])) {
            return '';
        }

        try {
            return ERPOutput::getDocumentHtml(
                $entity['id'],
                $entity['type'],
                null,
                ERPOutput::getOutputTemplateProviderByPackage($template['provider']),
                $template['id'],
                true
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }
    },
    ['entity', 'template'],
    'Permission::checkAdminUser'
);
