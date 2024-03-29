<?php

/**
 * Get contact email address.
 *
 * @param int $userId
 * @return string
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_userData_getUserEmailAddresses',
    function ($userId) {
        $emailAddresses = [];

        try {
            $User = QUI::getUsers()->get((int)$userId);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return $emailAddresses;
        }

        if (!empty($User->getAttribute('email'))) {
            $emailAddresses[] = $User->getAttribute('email');
        }

        /** @var \QUI\Users\Address $Address */
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
