<?php

/**
 * This file contains package_quiqqer_products_ajax_products_calcNettoPrice
 */

use QUI\ERP\Tax\TaxEntry;
use QUI\ERP\Tax\TaxType;
use QUI\ERP\Tax\Utils as TaxUtils;
use QUI\System\Log;

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
    'package_quiqqer_erp_ajax_calcBruttoPrice',
    function ($price, $formatted, $vat) {
        $price = QUI\ERP\Money\Price::validatePrice($price);
        $Currency = QUI\ERP\Defaults::getCurrency();

        if (empty($vat)) {
            $Area = QUI\ERP\Defaults::getArea();

            try {
                $TaxType = TaxUtils::getTaxTypeByArea($Area);
                $TaxEntry = TaxUtils::getTaxEntry($TaxType, $Area);
            } catch (QUI\Exception $e) {
                Log::addError($e->getMessage(), [
                    'ajax' => 'package_quiqqer_erp_ajax_calcBruttoPrice',
                    'price' => $price,
                    'vat' => $vat
                ]);

                if (isset($formatted) && $formatted) {
                    return $Currency->format($price);
                }

                return $price;
            }

            $vat = $TaxEntry->getValue();
        }

        $vat = (100 + $vat) / 100;

        $price = $price * $vat;
        $price = round($price, $Currency->getPrecision());

        if (isset($formatted) && $formatted) {
            return $Currency->format($price);
        }

        return $price;
    },
    ['price', 'formatted', 'vat']
);
