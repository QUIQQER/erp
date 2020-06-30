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

        return $OutputTemplate->getHTML();
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
     * Get document as e-mail with PDF attachment
     *
     * @param string|int $entityId
     * @param string $entityType
     * @param OutputProviderInterface $OutputProvider (optional)
     * @param OutputTemplateProviderInterface $TemplateProvider (optional)
     * @param string $template (optional)
     * @param string $recipientEmail (optional)
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
        string $recipientEmail = null
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

        $outputHtml = $OutputTemplate->getHTML();
        $pdfFile    = $OutputTemplate->getPDFDocument()->createPDF();

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
        $Mailer->addAttachment($pdfFile);

        $Mailer->setSubject($OutputProvider::getMailSubject($entityId));
        $Mailer->setBody($OutputProvider::getMailBody($entityId, $outputHtml));

        $Mailer->send();

// @todo fire event

// Delete PDF file after send
        if (\file_exists($pdfFile)) {
            \unlink($pdfFile);
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
     * Get all available ERP Output provider classes
     *
     * @return array - Provider classes
     */
    public static function getAllOutputProviders()
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
                $class = $packageProvider['erpOutput'][0];

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
