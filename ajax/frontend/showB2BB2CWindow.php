<?php

/**
 * This file contains package_quiqqer_erp_ajax_frontend_showB2BB2CWindow
 */

/**
 * Show the b2b b2c customer window
 *
 * @param string|int|float $value
 * @return int
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_frontend_showB2BB2CWindow',
    function () {
        $User = QUI::getUserBySession();
        $Package = QUI::getPackage('quiqqer/erp');

        // user dont get the window
        if (!($User instanceof QUI\Users\Nobody)) {
            return false;
        }

        $status = QUI::getSession()->get('quiqqer.erp.b2b.status');

        if (is_numeric($status)) {
            return false;
        }

        $areas = $Package->getConfig()->get('general', 'customerRequestWindow');
        $areas = explode(',', $areas);

        if (QUI\ERP\Areas\Utils::isUserInAreas($User, $areas)) {
            return true;
        }

        return false;
    },
    false
);
