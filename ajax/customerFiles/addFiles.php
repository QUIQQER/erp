<?php

/**
 * This file contains package_quiqqer_products_ajax_products_calcNettoPrice
 */

use QUI\ERP\Processes;

/**
 * add multiple files to the customer files of the entity
 *
 * @param string $hash - Entity hash
 * @param string $fileHashes - json array
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_customerFiles_addFiles',
    function ($hash, $fileHashes) {
        $Entity = (new Processes())->getEntity($hash);
        $fileHashes = json_decode($fileHashes, true) ?? [];

        foreach ($fileHashes as $fileHash) {
            $Entity->addCustomerFile($fileHash);
        }
    },
    ['hash', 'fileHashes']
);
