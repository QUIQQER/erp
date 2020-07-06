<?php

use QUI\ERP\Output\Output as ERPOutput;
use QUI\Utils\Security\Orthos;

/**
 * Returns e-mail data for an output document
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_getMailData',
    function ($entityId, $entityType) {
        $OutputProvider = ERPOutput::getOutputProviderByEntityType(Orthos::clear($entityType));
        $mailData       = [
            'subject' => '',
            'content' => ''
        ];

        if (empty($OutputProvider)) {
            return $mailData;
        }

        $mailData['subject'] = $OutputProvider::getMailSubject($entityId);
        $mailData['content'] = $OutputProvider::getMailBody($entityId);

        return $mailData;
    },
    ['entityId', 'entityType'],
    'Permission::checkAdminUser'
);
