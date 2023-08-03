<?php

/**
 * Filter article description
 *
 * @param string $description
 * @return string
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_utils_sanitizeArticleDescription',
    function ($description) {
        return \QUI\ERP\Utils\Utils::sanitizeArticleDescription($description);
    },
    ['description'],
    'Permission::checkAdminUser'
);
