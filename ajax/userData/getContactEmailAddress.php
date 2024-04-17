<?php

/**
 * Get contact email address.
 *
 * @param int $userId
 * @return string
 */

use QUI\ERP\Customer\Utils;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_userData_getContactEmailAddress',
    function ($userId) {
        try {
            $email = Utils::getInstance()->getContactEmailByCustomer(
                QUI::getUsers()->get($userId)
            );
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return '';
        }

        return $email ?: '';
    },
    ['userId'],
    'Permission::checkAdminUser'
);
