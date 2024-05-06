<?php

/**
 * Get contact email address.
 *
 * @param int $userId
 * @return string
 */

use QUI\Users\Address;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_userData_getUserEmailAddresses',
    function ($userId) {
        $emailAddresses = [];

        try {
            $User = QUI::getUsers()->get($userId);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return $emailAddresses;
        }

        if (!empty($User->getAttribute('email'))) {
            $emailAddresses[] = $User->getAttribute('email');
        }

        /** @var Address $Address */
        foreach ($User->getAddressList() as $Address) {
            foreach ($Address->getMailList() as $email) {
                $emailAddresses[] = $email;
            }
        }

        return array_values(array_unique($emailAddresses));
    },
    ['userId'],
    'Permission::checkAdminUser'
);
