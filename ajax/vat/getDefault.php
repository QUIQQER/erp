<?php

/**
 * This file contains package_quiqqer_erp_ajax_vat_getDefault
 */

/**
 * Return the default vat
 *
 * @return float|int
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_vat_getDefault',
    function () {
        $DefaultTaxType = QUI\ERP\Tax\Utils::getTaxTypeByArea(QUI\ERP\Defaults::getArea());
        $DefaultTaxEntry = QUI\ERP\Tax\Utils::getTaxEntry($DefaultTaxType, QUI\ERP\Defaults::getArea());

        return $DefaultTaxEntry->getValue();
    }
);
