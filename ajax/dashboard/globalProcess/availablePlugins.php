<?php

/**
 * This file contains package_quiqqer_erp_ajax_dashboard_globalProcess_availablePlugins
 */

use QUI\ERP\Processes;

QUI::$Ajax->registerFunction(
    'package_quiqqer_erp_ajax_dashboard_globalProcess_availablePlugins',
    function () {
        $PackageManager = QUI::getPackageManager();
        $Processes = new Processes();
        $plugins = [];

        foreach ($Processes->getWantedPluginList() as $plugin) {
            if ($PackageManager->isInstalled($plugin)) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    },
    [],
    ['Permission::checkAdminUser']
);
