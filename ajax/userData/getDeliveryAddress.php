<?php

/**
 * Returns delivery address of the user
 *
 * @param int $userId
 * @return array
 */

use QUI\Users\Address;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_userData_getDeliveryAddress',
    function ($userId) {
        try {
            $User = QUI::getUsers()->get($userId);
            $address = $User->getAttribute('quiqqer.delivery.address');
            $Address = $User->getAddress($address);

            return $Address->getAttributes();
        } catch (QUI\Exception) {
            return false;
        }
    },
    ['userId'],
    'Permission::checkAdminUser'
);
