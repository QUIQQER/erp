<?php

/**
 * This file contains package_quiqqer_erp_ajax_products_parseProductToArticle
 */

use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Products;

/**
 *
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_products_parseProductToArticle',
    function ($productId, $attributes, $user, $fields) {
        $user       = \json_decode($user, true);
        $fields     = \json_decode($fields, true);
        $attributes = \json_decode($attributes, true);
        $User       = null;
        $Locale     = QUI::getLocale();

        if (!empty($user)) {
            try {
                $User = QUI\ERP\User::convertUserDataToErpUser($user);
            } catch (QUI\Exception $Exception) {
                if (!isset($user['uid'])) {
                    throw $Exception;
                }

                $User = QUI::getUsers()->get($user['uid']);
            }

            $Locale = $User->getLocale();
        }

        try {
            $Product = Products::getProduct((int)$productId);

            foreach ($attributes as $field => $value) {
                if (\strpos($field, 'field-') === false) {
                    continue;
                }

                $field = \str_replace('field-', '', $field);
                $Field = $Product->getField((int)$field);

                $Field->setValue($value);
            }

            // look if the invoice text field has values
            try {
                $Description = $Product->getField(Fields::FIELD_SHORT_DESC);
                $InvoiceText = $Product->getField(
                    QUI\ERP\Constants::INVOICE_PRODUCT_TEXT_ID
                );

                if (!$InvoiceText->isEmpty()) {
                    $Description->setValue($InvoiceText->getValue());
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addNotice($Exception->getMessage());
            }


            // create unique product, to create the ERP Article, so invoice can work with it
            $Unique = $Product->createUniqueProduct($User);

            if (isset($attributes['quantity'])) {
                $Unique->setQuantity($attributes['quantity']);
            }

            $Unique->calc();
            $result = $Unique->toArticle($Locale)->toArray();

            if (empty($fields)) {
                return $result;
            }

            $fieldResult = [];

            foreach ($fields as $fieldId) {
                try {
                    $Field = $Product->getField($fieldId);

                    $fieldResult[$Field->getId()] = $Field->getValue();
                } catch (QUI\Exception $Exception) {
                }
            }

            $result['fields'] = $fieldResult;

            return $result;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::write($Exception->getMessage());
        }

        return [];
    },
    ['productId', 'attributes', 'user', 'fields'],
    'Permission::checkAdminUser'
);
