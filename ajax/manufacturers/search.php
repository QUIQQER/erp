<?php

/**
 * Execute the customer search
 *
 * @return array
 */

use QUI\ERP\Manufacturers;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_manufacturers_search',
    function ($params) {
        $searchParams = Orthos::clearArray(json_decode($params, true));

        $results = Manufacturers::search($searchParams);
        $count = Manufacturers::search($searchParams, true);
        $Grid = new Grid($searchParams);

        if (!is_array($results)) {
            $results = [];
        }

        if (!is_int($count)) {
            $count = count($count);
        }

        return $Grid->parseResult(
            Manufacturers::parseListForGrid($results),
            $count
        );
    },
    ['params'],
    'Permission::checkAdminUser'
);
