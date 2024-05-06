<?php

/**
 * This file contains package_quiqqer_products_ajax_products_calcNettoPrice
 */

use QUI\ERP\Processes;

/**
 * Return the entity files
 *
 * @param string $hash - Entity hash
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_customerFiles_update',
    function ($hash, $files) {
        $files = json_decode($files, true);
        $Entity = (new Processes())->getEntity($hash);

        if (method_exists($Entity, 'setCustomFiles')) {
            $Entity->setCustomFiles($files);
        }
    },
    ['hash', 'files']
);
