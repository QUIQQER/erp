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
    'package_quiqqer_erp_ajax_customerFiles_addFile',
    function ($hash, $fileHash) {
        $Entity = (new Processes())->getEntity($hash);
        $Entity->addCustomerFile($fileHash);
    },
    ['hash', 'fileHash']
);
