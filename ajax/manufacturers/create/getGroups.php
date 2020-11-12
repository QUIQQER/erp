<?php

use QUI\ERP\Manufacturers;

/**
 * Get details of manufacturer groups
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_create_getGroups',
    function () {
        $Groups = QUI::getGroups();
        $groups = [];

        foreach (Manufacturers::getManufacturerGroupIds() as $groupId) {
            $Group = $Groups->get($groupId);

            $groups[] = [
                'id'   => $Group->getId(),
                'name' => $Group->getName()
            ];
        }

        return $groups;
    },
    [],
    'Permission::checkAdminUser'
);
