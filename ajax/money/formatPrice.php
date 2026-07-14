<?php

/**
 * This file contains package_quiqqer_erp_ajax_money_formatPrice
 */

use QUI\ERP\Defaults;

/**
 * Format a price for the admin
 *
 * @param string|int|float $value
 * @return string
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_money_formatPrice',
    function ($price, $language) {
        $Locale = QUI::getSystemLocale();
        $amount = QUI\ERP\Money\Price::parsePrice($price);
        // Keep legacy compatibility: NumberFormatter treated null as 0 for invalid prices.
        // Throwing an exception would be safer but is reserved for a future breaking change.
        $amount = (float)($amount ?? 0.0);

        if (!empty($language)) {
            $Locale->setCurrent($language);
        }

        $localeCode = $Locale->getLocalesByLang($Locale->getCurrent());

        $Formatter = new NumberFormatter(
            $localeCode[0],
            NumberFormatter::CURRENCY,
            $Locale->getAccountingCurrencyPattern()
        );

        $Formatter->setSymbol(NumberFormatter::CURRENCY_SYMBOL, '');

        return $Formatter->formatCurrency(
            $amount,
            Defaults::getCurrency()->getCode()
        );
    },
    ['price', 'language'],
    'Permission::checkAdminUser'
);
