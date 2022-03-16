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
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_money_formatPrice',
    function ($price, $language) {
        $Locale = QUI::getSystemLocale();
        $amount = QUI\ERP\Money\Price::validatePrice($price);

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
