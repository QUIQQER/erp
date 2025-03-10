<?php

/**
 * Returns e-mail data for an output document
 *
 * @param string|int $entityId
 * @param string $entityType
 * @param string $template
 * @param string $templateProvider
 * @param string $mailRecipient
 * @param string $mailSubject (optional)
 * @param string $mailContent (optional)
 * @param array $mailAttachmentMediaFileIds (optional)
 *
 * @return void
 */

use QUI\ERP\Output\Output as ERPOutput;
use QUI\Permissions\Permission;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_sendMail',
    function (
        $entityId,
        $entityType,
        $template,
        $templateProvider,
        $mailRecipient,
        $mailSubject,
        $mailContent,
        $mailAttachmentMediaFileIds
    ) {
        try {
            $entityType = Orthos::clear($entityType);

            $outputProvider = ERPOutput::getOutputProviderByEntityType($entityType);
            $OutputProvider = new $outputProvider();
            $TemplateProvider = ERPOutput::getOutputTemplateProviderByPackage(Orthos::clear($templateProvider));

            if (empty($TemplateProvider)) {
                $TemplateProvider = ERPOutput::getDefaultOutputTemplateProviderForEntityType($entityType);
            }

            $attachedMediaFiles = [];

            if (
                !empty($mailAttachmentMediaFileIds) &&
                Permission::hasPermission(ERPOutput::PERMISSION_ATTACH_EMAIL_FILES)
            ) {
                $Project = QUI::getRewrite()->getProject();

                if ($Project) {
                    $Project = QUI::getProjectManager()->getStandard();
                }

                if ($Project) {
                    $Media = $Project->getMedia();
                    $mailAttachmentMediaFileIds = json_decode($mailAttachmentMediaFileIds, true);

                    foreach ($mailAttachmentMediaFileIds as $fileId) {
                        if (empty($fileId)) {
                            continue;
                        }

                        try {
                            $File = $Media->get((int)$fileId);

                            if ($File instanceof QUI\Projects\Media\File || $File instanceof QUI\Projects\Media\Image) {
                                $attachedMediaFiles[] = $File;
                            }
                        } catch (Exception $Exception) {
                            QUI\System\Log::writeException($Exception);
                        }
                    }
                }
            }

            ERPOutput::sendPdfViaMail(
                $entityId,
                $entityType,
                $OutputProvider,
                $TemplateProvider,
                Orthos::clear($template),
                Orthos::clear($mailRecipient),
                $mailSubject,
                $mailContent,
                $attachedMediaFiles
            );
        } catch (QUI\ERP\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            throw $Exception;
        } catch (Exception $Exception) {
            if ($Exception->getCode() === 403) {
                throw $Exception;
            } else {
                QUI\System\Log::writeException($Exception);

                throw new \QUI\Exception([
                    'quiqqer/erp',
                    'exception.ajax.output.sendMail.error'
                ]);
            }
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get('quiqqer/erp', 'Output.send.success')
        );
    },
    [
        'entityId',
        'entityType',
        'template',
        'templateProvider',
        'mailRecipient',
        'mailSubject',
        'mailContent',
        'mailAttachmentMediaFileIds'
    ],
    'Permission::checkAdminUser'
);
