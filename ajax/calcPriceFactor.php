<?php

/**
 * This file contains package_quiqqer_erp_ajax_calculatePriceFactor
 */

/**
 *
 */

use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ERP\Money\Price;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_calcPriceFactor',
    function ($price, $vat, $currency) {
        $Currency = CurrencyHandler::getCurrency($currency);
        $price = Price::validatePrice($price);
        $vat = floatval($vat);

        /* auskommentiert weil: quiqqer/erp/-/issues/78#note_144725
        if (empty($vat)) {
            $Area     = QUI\ERP\Defaults::getArea();
            $TaxType  = QUI\ERP\Tax\Utils::getTaxTypeByArea($Area);
            $TaxEntry = QUI\ERP\Tax\Utils::getTaxEntry($TaxType, $Area);

            $vat = $TaxEntry->getValue();
        }
        */

        $nettoSum = $price;
        $nettoSumFormatted = $Currency->format($price);
        $sum = $price * (($vat + 100) / 100);
        $sumFormatted = $Currency->format($sum);

        $valueText = $sumFormatted;

        if (!str_contains($valueText, '+') && !str_contains($valueText, '-')) {
            $valueText = '+' . $valueText;
        }

        return [
            'nettoSum' => $nettoSum,
            'nettoSumFormatted' => $nettoSumFormatted,
            'sum' => $sum,
            'sumFormatted' => $sumFormatted,
            'valueText' => $valueText
        ];
    },
    ['price', 'vat', 'currency'],
    'Permission::checkAdminUser'
);
