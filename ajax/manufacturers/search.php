<?php

/**
 * Execute the customer search
 *
 * @return array
 */

use QUI\ERP\Manufacturers;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_search',
    function ($params) {
        $searchParams = Orthos::clearArray(\json_decode($params, true));

        $results = Manufacturers::search($searchParams);
        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            Manufacturers::parseListForGrid($results),
            Manufacturers::search($searchParams, true)
        );
    },
    ['params'],
    'Permission::checkAdminUser'
);
