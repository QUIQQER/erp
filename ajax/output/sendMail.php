<?php

use QUI\ERP\Output\Output as ERPOutput;
use QUI\Utils\Security\Orthos;

/**
 * Returns e-mail data for an output document
 *
 * @param string|int $entityId
 * @param string $entityType
 * @param string $template
 * @param string $templateProvider
 * @param string $mailSubject (optional)
 * @param string $mailContent (optional)
 *
 * @return void
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_sendMail',
    function ($entityId, $entityType, $template, $templateProvider, $mailSubject, $mailContent) {
        try {
            $entityType = Orthos::clear($entityType);

            $OutputProvider   = ERPOutput::getOutputProviderByEntityType($entityType);
            $TemplateProvider = ERPOutput::getOutputTemplateProviderByPackage(Orthos::clear($templateProvider));

            if (empty($TemplateProvider)) {
                $TemplateProvider = ERPOutput::getDefaultOutputTemplateProviderForEntityType($entityType);
            }

            ERPOutput::sendPdfViaMail(
                $entityId,
                $entityType,
                $OutputProvider,
                $TemplateProvider,
                Orthos::clear($template),
                null,
                $mailSubject,
                $mailContent
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            throw new \QUI\Exception([
                'quiqqer/erp',
                'exception.ajax.output.sendMail.error'
            ]);
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get('quiqqer/erp', 'Output.send.success')
        );
    },
    ['entityId', 'entityType', 'template', 'templateProvider', 'mailSubject', 'mailContent'],
    'Permission::checkAdminUser'
);
