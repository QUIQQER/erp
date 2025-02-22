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
    'package_quiqqer_erp_ajax_calcNettoPrice',
    function ($price, $formatted, $vat) {
        $price = QUI\ERP\Money\Price::validatePrice($price);

        if (empty($price)) {
            if (isset($formatted) && $formatted) {
                return QUI\ERP\Defaults::getCurrency()->format(0);
            }

            return 0;
        }

        if (empty($vat) && !is_numeric($vat)) {
            $Area = QUI\ERP\Defaults::getArea();

            try {
                $TaxType = TaxUtils::getTaxTypeByArea($Area);
                $TaxEntry = TaxUtils::getTaxEntry($TaxType, $Area);
            } catch (QUI\Exception $Exception) {
                Log::addError($Exception->getMessage(), [
                    'price' => $price,
                    'formatted' => $formatted,
                    'vat' => $vat,
                    'ajax' => 'package_quiqqer_erp_ajax_calcNettoPrice'
                ]);

                if (isset($formatted) && $formatted) {
                    return QUI\ERP\Defaults::getCurrency()->format($price);
                }

                return $price;
            }

            $vat = $TaxEntry->getValue();
        }

        $vat = ($vat / 100) + 1;
        $netto = $price / $vat;
        $netto = round($netto, QUI\ERP\Defaults::getPrecision());

        // gegenrechnung
        $precision = QUI\ERP\Defaults::getPrecision();
        $bruttoInput = round($price, $precision);

        $decimalParts = explode('.', (string)$bruttoInput);
        $inputPrecision = isset($decimalParts[1]) ? strlen($decimalParts[1]) : 0;

        $brutto = round($netto, $precision) * $vat;
        $brutto = round($brutto, $inputPrecision);

        if ($brutto != $bruttoInput) {
            $netto = round($netto, $precision);
            $brutto = round($netto * $vat, $inputPrecision);

            if ($brutto != $bruttoInput) {
                for ($i = 0; $i < 10; $i++) {
                    $nettoCheck = (float)substr((string)$netto, 0, -$precision);
                    $bruttoCheck = round($nettoCheck * $vat, $inputPrecision);

                    if ($bruttoCheck == $bruttoInput) {
                        $netto = $nettoCheck;
                        break;
                    }
                }
            }
        }

        if (isset($formatted) && $formatted) {
            return QUI\ERP\Defaults::getCurrency()->format($netto);
        }

        //$netto = round($netto, QUI\ERP\Defaults::getPrecision());

        return $netto;
    },
    ['price', 'formatted', 'vat']
);
