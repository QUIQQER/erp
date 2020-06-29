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

                $class = $packageProvider['erpOutput'][0];

                if (!\class_exists($class)) {
                    continue;
                }

                return $class;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

//        QUI\Cache\Manager::set($cache, $provider);

        return false;
    }

    /**
     * Get HTML output for a specific document
     *
     * @param string $entityProvider
     * @param string $entityId
     * @param string $entityType
     * @param string $templateProvider (optional)
     * @param string $template (optional)
     *
     * @return string
     */
    public static function getDocumentHtml(
        string $entityProvider,
        string $entityId,
        string $entityType,
        string $templateProvider = null,
        string $template = null
    ) {
        if (empty($templateProvider)) {
            $OutputTemplateProvider = self::getDefaultOutputTemplateProviderForEntityType($entityType);
        } else {
            $OutputTemplateProvider = self::getOutputTemplateProviderByPackage($templateProvider);
        }

        $OutputProvider = self::getOutputProviderByPackage($entityProvider);

        $OutputTemplate = new OutputTemplate(
            $OutputTemplateProvider,
            $entityType,
            $template
        );

        $Engine       = $OutputTemplate->getEngine();
        $templateData = $OutputProvider::getTemplateData($entityId, $OutputTemplate);
        $Engine->assign($templateData);

        return $OutputTemplate->render();
    }

    /**
     * Get available templates for $entityType (e.g. "Invoice", "InvoiceTemporary" etc.)
     *
     * @param string $package
     * @return OutputTemplateProviderInterface|false - OutputProvider class (static) or false if
     * @throws QUI\Exception
     */
    public static function getOutputTemplateProviderByPackage(string $package)
    {
        foreach (self::getAllOutputTemplateProviders() as $provider) {
            if ($provider['package'] === $package) {
                return $provider['class'];
            }
        }

        return false;
    }

    /**
     * Get available templates for $entityType (e.g. "Invoice", "InvoiceTemporary" etc.)
     *
     * @param string $entityType
     * @return array
     * @throws QUI\Exception
     */
    public static function getTemplates(string $entityType)
    {
        $templates = [];

        foreach (self::getAllOutputTemplateProviders() as $provider) {
            /** @var OutputTemplateProviderInterface $class */
            $class   = $provider['class'];
            $package = $provider['package'];

            $providerTemplates = $class::getTemplates($entityType);

            foreach ($providerTemplates as $providerTemplate) {
                $providerTemplate['provider'] = $package;
                $templates[]                  = $providerTemplate;
            }
        }

        return $templates;
    }

    /**
     * Return default Output Template provider class for a specific entity type
     *
     * @param string $entityType
     * @return OutputTemplateProviderInterface|false
     */
    public static function getDefaultOutputTemplateProviderForEntityType(string $entityType)
    {
        foreach (self::getAllOutputTemplateProviders() as $provider) {
            /** @var OutputTemplateProviderInterface $class */
            $class             = $provider['class'];
            $providerTemplates = $class::getTemplates($entityType);

            if (!empty($providerTemplates)) {
                return $class;
            }
        }

        return false;
    }

    /**
     * Get all available ERP Output Template provider classes
     *
     * @return array - Provider classes
     */
    public static function getAllOutputTemplateProviders()
    {
        $packages        = QUI::getPackageManager()->getInstalled();
        $providerClasses = [];

        foreach ($packages as $installedPackage) {
            try {
                $Package = QUI::getPackage($installedPackage['name']);

                if (!$Package->isQuiqqerPackage()) {
                    continue;
                }

                $packageProvider = $Package->getProvider();

                if (empty($packageProvider['erpOutputTemplate'])) {
                    continue;
                }

                /** @var OutputTemplateProviderInterface $class */
                $class = $packageProvider['erpOutputTemplate'][0];

                if (!\class_exists($class)) {
                    continue;
                }

                $providerClasses[] = [
                    'class'   => $class,
                    'package' => $installedPackage['name']
                ];
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $providerClasses;
    }
}
