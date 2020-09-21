<?php

namespace QUI\ERP\Output;

use QUI;
use QUI\ERP\Accounting\Invoice\Settings;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

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
     * @return OutputProviderInterface|false - OutputProvider class (static) or false if none found
     */
    public static function getOutputProviderByPackage(string $package)
    {
        foreach (self::getAllOutputProviders() as $outputProvider) {
            if ($outputProvider['package'] === $package) {
                return $outputProvider['class'];
            }
        }

        return false;
    }

    /**
     * Get the OutputProvider for a specific entity type
     *
     * @param string $entityType
     * @return OutputProviderInterface|false - OutputProvider class (static) or false if none found
     */
    public static function getOutputProviderByEntityType(string $entityType)
    {
        foreach (self::getAllOutputProviders() as $outputProvider) {
            /** @var OutputProviderInterface $class */
            $class = $outputProvider['class'];

            if ($class::getEntityType() === $entityType) {
                return $class;
            }
        }

        return false;
    }

    /**
     * Get HTML output for a specific document
     *
     * @param string|int $entityId
     * @param string $entityType
     * @param OutputProviderInterface $OutputProvider (optional)
     * @param OutputTemplateProviderInterface $TemplateProvider (optional)
     * @param string $template (optional)
     * @param bool $preview (optional) - Get preview HTML
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDocumentHtml(
        $entityId,
        string $entityType,
        $OutputProvider = null,
        $TemplateProvider = null,
        string $template = null,
        bool $preview = false
    ) {
        if (empty($OutputProvider)) {
            $OutputProvider = self::getOutputProviderByEntityType($entityType);
        }

        if (empty($OutputProvider)) {
            throw new QUI\Exception('No output provider found for entity type "'.$entityType.'"');
        }

        if (empty($TemplateProvider)) {
            $TemplateProvider = self::getDefaultOutputTemplateProviderForEntityType($entityType);
        }

        if (empty($TemplateProvider)) {
            throw new QUI\Exception('No default output template provider found for entity type "'.$entityType.'"');
        }

        $OutputTemplate = new OutputTemplate(
            $TemplateProvider,
            $OutputProvider,
            $entityId,
            $entityType,
            $template
        );

        return $OutputTemplate->getHTML($preview);
    }

    /**
     * Get HTML output for a specific document
     *
     * @param string|int $entityId
     * @param string $entityType
     * @param OutputProviderInterface $OutputProvider (optional)
     * @param OutputTemplateProviderInterface $TemplateProvider (optional)
     * @param string $template (optional)
     *
     * @return QUI\HtmlToPdf\Document
     *
     * @throws QUI\Exception
     */
    public static function getDocumentPdf(
        $entityId,
        string $entityType,
        $OutputProvider = null,
        $TemplateProvider = null,
        string $template = null
    ) {
        if (empty($OutputProvider)) {
            $OutputProvider = self::getOutputProviderByEntityType($entityType);
        }

        if (empty($TemplateProvider)) {
            $TemplateProvider = self::getDefaultOutputTemplateProviderForEntityType($entityType);
        }

        $OutputTemplate = new OutputTemplate(
            $TemplateProvider,
            $OutputProvider,
            $entityId,
            $entityType,
            $template
        );

        return $OutputTemplate->getPDFDocument();
    }

    /**
     * Get HTML output for a specific document
     *
     * @param string|int $entityId
     * @param string $entityType
     *
     * @return string
     */
    public static function getDocumentPdfDownloadUrl(
        $entityId,
        string $entityType
    ) {
        $url = URL_OPT_DIR.'quiqqer/erp/bin/output/frontend/download.php?';
        $url .= \http_build_query([
            'id' => $entityId,
            't'  => $entityType
        ]);

        return $url;
    }

    /**
     * Send document as e-mail with PDF attachment
     *
     * @param string|int $entityId
     * @param string $entityType
     * @param OutputProviderInterface $OutputProvider (optional)
     * @param OutputTemplateProviderInterface $TemplateProvider (optional)
     * @param string $template (optional)
     * @param string $recipientEmail (optional)
     * @param string $mailSubject (optional)
     * @param string $mailContent (optional)
     *
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function sendPdfViaMail(
        $entityId,
        string $entityType,
        $OutputProvider = null,
        $TemplateProvider = null,
        string $template = null,
        string $recipientEmail = null,
        string $mailSubject = null,
        string $mailContent = null
    ) {
        if (empty($OutputProvider)) {
            $OutputProvider = self::getOutputProviderByEntityType($entityType);
        }

        if (empty($TemplateProvider)) {
            $TemplateProvider = self::getDefaultOutputTemplateProviderForEntityType($entityType);
        }

        $OutputTemplate = new OutputTemplate(
            $TemplateProvider,
            $OutputProvider,
            $entityId,
            $entityType,
            $template
        );

        $pdfFile = $OutputTemplate->getPDFDocument()->createPDF();

        // Re-name PDF
        $pdfDir   = QUI::getPackage('quiqqer/erp')->getVarDir();
        $mailFile = $pdfDir.$OutputProvider::getDownloadFileName($entityId).'.pdf';
        \rename($pdfFile, $mailFile);

        if (empty($recipientEmail) || !QUI\Utils\Security\Orthos::checkMailSyntax($recipientEmail)) {
            $recipientEmail = $OutputProvider::getEmailAddress($entityId);
        }

        if (empty($recipientEmail)) {
            throw new QUI\ERP\Exception([
                'quiqqer/erp',
                'exception.Output.sendPdfViaMail.missing_recipient'
            ]);
        }

        // mail send
        $Mailer = new QUI\Mail\Mailer();

        $Mailer->addRecipient($recipientEmail);
        $Mailer->addAttachment($mailFile);

        if (!empty($mailSubject)) {
            $Mailer->setSubject($mailSubject);
        } else {
            $Mailer->setSubject($OutputProvider::getMailSubject($entityId));
        }

        if (!empty($mailContent)) {
            $Mailer->setBody($mailContent);
        } else {
            $Mailer->setBody($OutputProvider::getMailBody($entityId));
        }

        QUI::getEvents()->fireEvent('quiqqerErpOutputSendMailBefore', [$entityId, $entityType, $recipientEmail, $Mailer]);

        $Mailer->send();

        QUI::getEvents()->fireEvent('quiqqerErpOutputSendMail', [$entityId, $entityType, $recipientEmail]);

        // Delete PDF file after send
        if (\file_exists($mailFile)) {
            \unlink($mailFile);
        }
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
     * @param string $entityType (optional) - Restrict to templates of $entityType [default: fetch templates for all entity types]
     * @return array
     */
    public static function getTemplates(string $entityType = null)
    {
        $templates       = [];
        $outputProviders = [];

        if (empty($entityType)) {
            $outputProviders = \array_column(self::getAllOutputProviders(), 'class');
        } else {
            $OutputProvider = self::getOutputProviderByEntityType($entityType);

            if ($OutputProvider) {
                $outputProviders[] = $OutputProvider;
            }
        }

        foreach (self::getAllOutputTemplateProviders() as $provider) {
            /** @var OutputTemplateProviderInterface $class */
            $class   = $provider['class'];
            $package = $provider['package'];

            /** @var OutputProviderInterface $OutputProvider */
            foreach ($outputProviders as $OutputProvider) {
                $entityType            = $OutputProvider::getEntityType();
                $defaultOutputTemplate = self::getDefaultOutputTemplateForEntityType($entityType);

                foreach ($class::getTemplates($entityType) as $providerTemplateId) {
                    $templateTitle = $class::getTemplateTitle($providerTemplateId);

                    if ($provider['isSystemDefault']) {
                        $templateTitle .= ' '.QUI::getLocale()->get('quiqqer/erp', 'output.default_template.suffix');
                    }

                    $isDefault = $defaultOutputTemplate['provider'] === $provider['package'] &&
                                 $defaultOutputTemplate['id'] === $providerTemplateId;

                    $providerTemplate = [
                        'id'              => $providerTemplateId,
                        'title'           => $templateTitle,
                        'provider'        => $package,
                        'isSystemDefault' => $provider['isSystemDefault'],
                        'isDefault'       => $isDefault,
                        'entityType'      => $entityType,
                        'entityTypeTitle' => $OutputProvider::getEntityTypeTitle()
                    ];

                    $templates[] = $providerTemplate;
                }
            }
        }

        // Sort so that system default is first
        \usort($templates, function ($a, $b) {
            if ($a['isSystemDefault']) {
                return -1;
            }

            if ($b['isSystemDefault']) {
                return 1;
            }

            return 0;
        });

        return $templates;
    }

    /**
     * Return default template id for a specific entity type
     *
     * @param string $entityType
     * @return array - Containting template ID and template provider package
     */
    public static function getDefaultOutputTemplateForEntityType(string $entityType)
    {
        $fallBackTemplate = [
            'id'                => 'system_default',
            'provider'          => 'quiqqer/erp-accounting-templates',
            'hideSystemDefault' => false
        ];

        try {
            $Conf             = QUI::getPackage('quiqqer/erp')->getConfig();
            $defaultTemplates = $Conf->get('output', 'default_templates');

            if (empty($defaultTemplates)) {
                return $fallBackTemplate;
            }

            $defaultTemplates = \json_decode($defaultTemplates, true);

            if (empty($defaultTemplates[$entityType])) {
                return $fallBackTemplate;
            }

            return $defaultTemplates[$entityType];
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return $fallBackTemplate;
        }
    }

    /**
     * Return default Output Template provider class for a specific entity type
     *
     * @param string $entityType
     * @return OutputTemplateProviderInterface|false
     */
    public static function getDefaultOutputTemplateProviderForEntityType(string $entityType)
    {
        $defaultEntityTypeTemplate = self::getDefaultOutputTemplateForEntityType($entityType);

        foreach (self::getAllOutputTemplateProviders() as $provider) {
            if ($provider['package'] === $defaultEntityTypeTemplate['provider']) {
                return $provider['class'];
            }
        }

        // Fallback: Choose next available provider
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
     * Get all available ERP Output provider classes
     *
     * @return array - Provider classes
     */
    protected static function getAllOutputProviders()
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

                if (empty($packageProvider['erpOutput'])) {
                    continue;
                }

                /** @var OutputProviderInterface $class */
                foreach ($packageProvider['erpOutput'] as $class) {
                    if (!\class_exists($class)) {
                        continue;
                    }

                    $providerClasses[] = [
                        'class'   => $class,
                        'package' => $installedPackage['name']
                    ];
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $providerClasses;
    }

    /**
     * Get all available ERP Output Template provider classes
     *
     * @return array - Provider classes
     */
    protected static function getAllOutputTemplateProviders()
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
                foreach ($packageProvider['erpOutputTemplate'] as $class) {
                    if (!\class_exists($class)) {
                        continue;
                    }

                    $providerClasses[] = [
                        'class'           => $class,
                        'package'         => $installedPackage['name'],
                        'isSystemDefault' => $installedPackage['name'] === 'quiqqer/erp-accounting-templates'
                    ];
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // Sort providers that system default is first
        \usort($providerClasses, function ($a, $b) {
            if ($a['isSystemDefault']) {
                return -1;
            }

            if ($b['isSystemDefault']) {
                return 1;
            }

            return 0;
        });

        return $providerClasses;
    }
}
