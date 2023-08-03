<?php

/**
 * Create a new manufacturer users
 *
 * @param string $manufacturerId
 * @param array $address
 * @param array $groups
 * @return integer - New user ID
 */

use QUI\ERP\Exception as ERPException;
use QUI\ERP\Manufacturers;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_create_newManufacturer',
    function ($manufacturerId, $address, $groupIds) {
        $address = Orthos::clearArray(\json_decode($address, true));
        $groupIds = Orthos::clearArray(\json_decode($groupIds, true));
        $manufacturerId = Orthos::clear($manufacturerId);

        try {
            $User = Manufacturers::createManufacturer($manufacturerId, $address, $groupIds);
        } catch (ERPException $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            throw $Exception;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            throw new ERPException([
                'quiqqer/erp',
                'exception.ajax.manufacturers.create.newManufacturer.error'
            ]);
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/erp',
                'message.ajax.manufacturers.create.newManufacturer.success',
                [
                    'manufacturerId' => $manufacturerId
                ]
            )
        );

        return $User->getId();
    },
    ['manufacturerId', 'address', 'groupIds'],
    'Permission::checkAdminUser'
);
