<?php

namespace QUI\ERP;

use QUI;

class PackageApiCoordinator extends QUI\Utils\Singleton
{
    public function getMenuItems()
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $provider = array();

        foreach ($packages as $package) {
            try {
                $Package = QUI::getPackage($package['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $provider = array_merge($provider, $Package->getProvider('erp'));
            } catch (QUI\Exception $exception) {
            }
        }

        $provider;
    }
}