<?php

/**
 * Get details of manufacturer groups
 *
 * @return array
 */

use QUI\ERP\Manufacturers;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_create_getGroups',
    function () {
        $Groups = QUI::getGroups();
        $groups = [];

        foreach (Manufacturers::getManufacturerGroupIds() as $groupId) {
            $Group = $Groups->get($groupId);

            $groups[] = [
                'id' => $Group->getUUID(),
                'name' => $Group->getName()
            ];
        }

        return $groups;
    },
    [],
    'Permission::checkAdminUser'
);
