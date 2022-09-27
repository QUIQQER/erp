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
        $price    = Price::validatePrice($price);

        if (empty($vat)) {
            $Area     = QUI\ERP\Defaults::getArea();
            $TaxType  = QUI\ERP\Tax\Utils::getTaxTypeByArea($Area);
            $TaxEntry = QUI\ERP\Tax\Utils::getTaxEntry($TaxType, $Area);

            $vat = $TaxEntry->getValue();
        }

        $nettoSum          = $price;
        $nettoSumFormatted = $Currency->format($price);
        $sum               = $price * (($vat + 100) / 100);
        $sumFormatted      = $Currency->format($sum);

        $valueText = $sumFormatted;

        if (strpos($valueText, '+') === false && strpos($valueText, '-') === false) {
            $valueText = '+' . $valueText;
        }

        return [
            'nettoSum'          => $nettoSum,
            'nettoSumFormatted' => $nettoSumFormatted,
            'sum'               => $sum,
            'sumFormatted'      => $sumFormatted,
            'valueText'         => $valueText
        ];
    },
    ['price', 'vat', 'currency'],
    'Permission::checkAdminUser'
);
