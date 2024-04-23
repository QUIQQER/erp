<?php

/**
 * Filter article description
 *
 * @param string $description
 * @return string
 */

use QUI\ERP\Utils\Utils;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_utils_sanitizeArticleDescription',
    function ($description) {
        return Utils::sanitizeArticleDescription($description);
    },
    ['description'],
    'Permission::checkAdminUser'
);
