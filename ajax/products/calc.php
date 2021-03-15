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

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_products_calc',
    function ($articles, $user) {
        $articles = \json_decode($articles, true);
        $user     = \json_decode($user, true);

        if (!\is_array($articles)) {
            $articles = [];
        }

        if (!empty($user)) {
            try {
                $User = QUI\ERP\User::convertUserDataToErpUser($user);
                $Calc = QUI\ERP\Accounting\Calc::getInstance($User);
            } catch (QUI\ERP\Exception $Exception) {
                $Calc = QUI\ERP\Accounting\Calc::getInstance();
            }
        } else {
            $Calc = QUI\ERP\Accounting\Calc::getInstance();
        }

        $User = $Calc->getUser();

        $Articles = new QUI\ERP\Accounting\ArticleList($articles);
        $Articles->setUser($User);
        $Articles->calc($Calc);

        $result = $Articles->toArray();

        // brutto stuff (for display)
        $User->setAttribute('RUNTIME_NETTO_BRUTTO_STATUS', QUI\ERP\Utils\User::IS_BRUTTO_USER);
        $Calc->setUser($User);
        $Articles->setUser($User);
        $Articles->recalculate($Calc);

        $brutto = $Articles->toArray();

        // discount
        foreach ($brutto['articles'] as $k => $article) {
            if (empty($article['discount'])) {
                continue;
            }

            $Discount = ArticleDiscount::unserialize($article['discount']);
            $Currency = $Discount->getCurrency();
            $vat      = $article['vat'] / 100 + 1;

            $nettoSum = $result['articles'][$k]['sum'];

            $brutto['articles'][$k]['display_quantity_sum'] = $brutto['articles'][$k]['display_sum'];
            $brutto['articles'][$k]['quantity_sum']         = $brutto['articles'][$k]['sum'];

            $unitPrice = $brutto['articles'][$k]['quantity_sum'] / $brutto['articles'][$k]['quantity'];
            $unitPrice = \round($unitPrice, $Currency->getPrecision());

            $brutto['articles'][$k]['unitPrice']         = $unitPrice;
            $brutto['articles'][$k]['display_unitPrice'] = $Currency->format($unitPrice);

            if ($Discount->getCalculation() !== QUI\ERP\Accounting\Calc::CALCULATION_COMPLEMENT) {
                $bruttoSum = $nettoSum * $vat;

                $brutto['articles'][$k]['discount']         = $Discount->getValue().'%';
                $brutto['articles'][$k]['display_discount'] = $Discount->getValue().'%';
                $brutto['articles'][$k]['display_sum']      = $Currency->format($bruttoSum);
                $brutto['articles'][$k]['sum']              = \round($bruttoSum, $Currency->getPrecision());
                continue;
            }

            $discount  = $Discount->getValue() * $vat;
            $bruttoSum = $nettoSum * $vat;

            $brutto['articles'][$k]['discount']         = $discount;
            $brutto['articles'][$k]['display_discount'] = $Currency->format($discount);
            $brutto['articles'][$k]['display_sum']      = $Currency->format($bruttoSum);
            $brutto['articles'][$k]['sum']              = \round($bruttoSum, $Currency->getPrecision());
        }

        $result['brutto'] = $brutto;

        return $result;
    },
    ['articles', 'user'],
    'Permission::checkAdminUser'
);
