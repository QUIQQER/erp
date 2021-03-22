<?php

/**
 * This file contains package_quiqqer_products_ajax_products_calcNettoPrice
 */

use QUI\ERP\Products\Utils\Calc;
use QUI\ERP\Tax\TaxEntry;
use QUI\ERP\Tax\TaxType;
use QUI\ERP\Tax\Utils as TaxUtils;

/**
 * Calculate the netto price
 *
 * @param integer|float $price - Price to calc (brutto price)
 * @param bool $formatted - output formatted?
 * @param integer $vat - optional
 *
 * @return float
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_calcNettoPrice',
    function ($price, $formatted, $vat) {
        $price = QUI\ERP\Money\Price::validatePrice($price);

        if (empty($vat)) {
            $Area    = QUI\ERP\Defaults::getArea();
            $TaxType = TaxUtils::getTaxTypeByArea($Area);

            if ($TaxType instanceof TaxType) {
                $TaxEntry = TaxUtils::getTaxEntry($TaxType, $Area);
            } elseif ($TaxType instanceof TaxEntry) {
                $TaxEntry = $TaxType;
            } else {
                if (isset($formatted) && $formatted) {
                    return QUI\ERP\Defaults::getCurrency()->format($price);
                }

                return $price;
            }

            $vat = $TaxEntry->getValue();
        }

        $vat   = ($vat / 100) + 1;
        $price = $price / $vat;

        if (isset($formatted) && $formatted) {
            return QUI\ERP\Defaults::getCurrency()->format($price);
        }

        $price = \round($price, QUI\ERP\Defaults::getPrecision());

        return $price;
    },
    ['price', 'formatted', 'vat']
);
