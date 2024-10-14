<?php

/**
 * Returns basic entity data used in OutputDialog
 *
 * @param string|int $entityId
 * @param string $entityType
 * @return array|false - Entity data or false
 */

use QUI\ERP\Output\Output as ERPOutput;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_output_getEntityData',
    function ($entityId, $entityType, $entityPlugin) {
        $OutputProvider = ERPOutput::getOutputProviderByEntityType(Orthos::clear($entityType));

        if (empty($OutputProvider)) {
            return false;
        }

        if (empty($entityPlugin)) {
            $entityPlugin = false;
        }

        $hideSystemDefaultTemplate = false;

        try {
            $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
            $defaultTemplates = $Conf->get('output', 'default_templates');

            if (!empty($defaultTemplates)) {
                $defaultTemplates = json_decode($defaultTemplates, true);

                if (!empty($defaultTemplates[$entityType])) {
                    $hideSystemDefaultTemplate = $defaultTemplates[$entityType]['hideSystemDefault'];
                }
            }
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        $uuid = '';
        $prefixedNumber = '';

        try {
            $Processes = new QUI\ERP\Processes();
            $Entity = $Processes->getEntity($entityId, $entityPlugin);
            $uuid = $Entity->getUUID();
            $prefixedNumber = $Entity->getPrefixedNumber();
        } catch (QUI\Exception) {
            if ($OutputProvider instanceof OutputProviderInterface) {
                $Entity = $OutputProvider->getEntity($entityId);

                if (method_exists($Entity, 'getUUID')) {
                    $uuid = $Entity->getUUID();
                } elseif (method_exists($Entity, 'getId')) {
                    $uuid = $Entity->getID();
                }

                if (method_exists($Entity, 'getPrefixedNumber')) {
                    $prefixedNumber = $Entity->getPrefixedNumber();
                }
            }
        }

        return [
            'email' => $OutputProvider::getEmailAddress(Orthos::clear($entityId)),
            'hideSystemDefaultTemplate' => $hideSystemDefaultTemplate,
            'uuid' => $uuid,
            'prefixedNumber' => $prefixedNumber
        ];
    },
    ['entityId', 'entityType', 'entityPlugin'],
    'Permission::checkAdminUser'
);
