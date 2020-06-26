<?php

namespace QUI\ERP\Output;

use QUI;
use QUI\ERP\Accounting\Invoice\Settings;

/**
 * Class Output
 *
 * Main handler for serving previews, PDFs and downloads for QUIQQER ERP documents
 */
class Output
{
    /**
     * Get the ERP Output Provider for a specific package
     *
     * @param string $package
     * @return OutputProviderInterface|false - OutputProvider class (static) or false if
     */
    public static function getOutputProviderByPackage(string $package)
    {
//        $cache = 'quiqqer/backendsearch/providers';
//
//        try {
//            return QUI\Cache\Manager::get($cache);
//        } catch (QUI\Cache\Exception $Exception) {
//        }

        $packages = QUI::getPackageManager()->getInstalled();
        $provider = [];

        foreach ($packages as $installedPackage) {
            try {
                $Package = QUI::getPackage($installedPackage['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                if ($Package->getName() !== $package) {
                    continue;
                }

                $packageProvider = $Package->getProvider();

                if (empty($packageProvider['erpOutput'])) {
                    continue;
                }

                if (!\class_exists($packageProvider['erpOutput'])) {
                    continue;
                }

                return $packageProvider['erpOutput'];
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                return false;
            }
        }

//        QUI\Cache\Manager::set($cache, $provider);

        return false;
    }

    public static function getTemplates(string $package)
    {
        $result   = [];
        $packages = QUI::getPackageManager()->getInstalled();
        $default  = Settings::get('invoice', 'template');

        $defaultIsDisabled = Settings::get('invoice', 'deactivateDefaultTemplate');

        foreach ($packages as $package) {
            $Package  = QUI::getPackage($package['name']);
            $composer = $Package->getComposerData();

            // @todo change if package name is changed to "quiqqer/erp-accounting-templates"
            if ($defaultIsDisabled && $Package->getName() === 'quiqqer/invoice-accounting-template') {
                continue;
            }

            if (!isset($composer['type'])) {
                continue;
            }

            // @todo change to "quiqqer-erp-template"
            if ($composer['type'] !== 'quiqqer-invoice-template') {
                continue;
            }

            $result[] = [
                'name'    => $Package->getName(),
                'title'   => $Package->getTitle(),
                'default' => $Package->getName() === $default ? 1 : 0
            ];
        }

        return $result;
    }
}
