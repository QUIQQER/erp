<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_calc
 */

/**
 * Calculates the current price of the article
 *
 * @return string
 */
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

        $Articles = new QUI\ERP\Accounting\ArticleList($articles);
        $Articles->setUser($Calc->getUser());
        $Articles->calc($Calc);

        $result = $Articles->toArray();

        return $result;
    },
    ['articles', 'user'],
    'Permission::checkAdminUser'
);
