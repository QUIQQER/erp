<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_calc
 */

/**
 * Calculates the current price of the article
 *
 * @return string
 */

use QUI\ERP\Accounting\ArticleDiscount;
use QUI\ERP\Defaults;
use QUI\ERP\Money\Price;

QUI::getAjax()->registerFunction(
    'package_quiqqer_erp_ajax_products_calc',
    function ($articles, $priceFactors, $user, $currency, $nettoInput) {
        $nettoInput = (int)$nettoInput;

        $articles = json_decode($articles, true);
        $user = json_decode($user, true);
        $priceFactors = json_decode($priceFactors, true);

        if (!is_array($articles)) {
            $articles = [];
        }

        if (!empty($user)) {
            try {
                $User = QUI\ERP\User::convertUserDataToErpUser($user);
                $Calc = QUI\ERP\Accounting\Calc::getInstance($User);
            } catch (QUI\ERP\Exception) {
                $Calc = QUI\ERP\Accounting\Calc::getInstance();
            }
        } else {
            $Calc = QUI\ERP\Accounting\Calc::getInstance();
        }

        $User = $Calc->getUser();

        if ($nettoInput) {
            $User->setAttribute('RUNTIME_NETTO_BRUTTO_STATUS', QUI\ERP\Utils\User::IS_NETTO_USER);
        } else {
            $User->setAttribute('RUNTIME_NETTO_BRUTTO_STATUS', QUI\ERP\Utils\User::IS_BRUTTO_USER);
        }

        $Articles = new QUI\ERP\Accounting\ArticleList($articles, $User);

        if (!empty($priceFactors)) {
            foreach ($priceFactors as $priceFactor) {
                foreach (['sum', 'nettoSum'] as $field) {
                    if (!isset($priceFactor[$field]) || is_numeric($priceFactor[$field])) {
                        continue;
                    }

                    $amount = Price::parsePrice($priceFactor[$field], $User->getLocale());

                    if ($amount === null) {
                        throw new QUI\ERP\Exception(
                            'Invalid price factor ' . $field,
                            400,
                            [$field => $priceFactor[$field]]
                        );
                    }

                    $priceFactor[$field] = $amount;
                }

                $Articles->addPriceFactor(
                    new QUI\ERP\Accounting\PriceFactors\Factor($priceFactor)
                );
            }
        }

        $Articles->calc($Calc);

        try {
            $Articles->setCurrency(
                QUI\ERP\Currency\Handler::getCurrency($currency)
            );
        } catch (QUI\Exception) {
        }

        $result = $Articles->toArray();

        // brutto stuff (for display)
        $User->setAttribute('RUNTIME_NETTO_BRUTTO_STATUS', QUI\ERP\Utils\User::IS_BRUTTO_USER);

        $Calc->setUser($User);
        $Articles->setUser($User);
        $Articles->recalculate($Calc);

        $brutto = $Articles->toArray();

        // discount stuff
        $Currency = $Articles->getCurrency();

        foreach ($brutto['articles'] as $k => $article) {
            $vat = $article['vat'] / 100 + 1;
            $bruttoSum = $article['sum'];
            $quantity = $article['quantity'];

            if ($quantity) {
                $unitPrice = $bruttoSum / $quantity;
                $unitPrice = round($unitPrice, Defaults::getPrecision());
            } else {
                $unitPrice = $article['unitPrice'] * $vat;
            }

            $brutto['articles'][$k]['display_quantity_sum'] = $brutto['articles'][$k]['display_sum'];
            $brutto['articles'][$k]['quantity_sum'] = $brutto['articles'][$k]['sum'];
            $brutto['articles'][$k]['unitPrice'] = $unitPrice;
            $brutto['articles'][$k]['display_unitPrice'] = $Currency->format(
                round($unitPrice, $Currency->getPrecision())
            );

            if (empty($article['discount'])) {
                continue;
            }

            $Discount = ArticleDiscount::unserialize($article['discount']);

            if ($Discount === null) {
                continue;
            }

            if ($Discount->getCalculation() !== QUI\ERP\Accounting\Calc::CALCULATION_COMPLEMENT) {
                $bruttoUnit = $result['articles'][$k]['unitPrice'] * $vat;
                $discount = $bruttoUnit * ($Discount->getValue() / 100);
                $uniqueDiscount = round($discount, $Currency->getPrecision());

                $brutto['articles'][$k]['discount'] = $Discount->getValue() . '%';
                $brutto['articles'][$k]['display_discount'] = $Discount->getValue() . '%';
            } else {
                $discount = $Discount->getValue() * $vat;
                $discount = round($discount, $Currency->getPrecision());
                $uniqueDiscount = $discount / $quantity;

                $brutto['articles'][$k]['discount'] = $discount;
                $brutto['articles'][$k]['display_discount'] = $Currency->format($discount);
            }

            $unitPrice = $brutto['articles'][$k]['unitPrice'];
            $unitPrice = $unitPrice + $uniqueDiscount;

            $brutto['articles'][$k]['display_sum'] = $Currency->format($bruttoSum);
            $brutto['articles'][$k]['sum'] = round($bruttoSum, $Currency->getPrecision());
            $brutto['articles'][$k]['display_unitPrice'] = $Currency->format($unitPrice);
            $brutto['articles'][$k]['unitPrice'] = round($unitPrice, $Currency->getPrecision());
            $brutto['articles'][$k]['display_quantity_sum'] = $Currency->format($unitPrice * $quantity);
            $brutto['articles'][$k]['quantity_sum'] = $unitPrice * $quantity;
        }

        $result['brutto'] = $brutto;

        return $result;
    },
    ['articles', 'priceFactors', 'user', 'currency', 'nettoInput'],
    'Permission::checkAdminUser'
);
