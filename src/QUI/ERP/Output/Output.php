<?php

namespace QUI\ERP\Output;

use Exception;
use QUI;

use function array_column;
use function class_exists;
use function file_exists;
use function http_build_query;
use function json_decode;
use function rename;
use function unlink;
use function usort;

/**
 * Class Output
 *
 * Main handler for serving previews, PDFs and downloads for QUIQQER ERP documents
 */
class Output
{
    /**
     * Permissions
     */
    const PERMISSION_ATTACH_EMAIL_FILES = 'quiqqer.erp.mail_editor_attach_files';

    /**
     * Get the ERP Output Provider for a specific package
     *
     * @param string $package
     * @return OutputProviderInterface|false - OutputProvider class (static) or false if none found
     */
    public static function getOutputProviderByPackage(string $package): bool|OutputProviderInterface
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
     * @return string|false - OutputProvider class (static) or false if none found
     */
    public static function getOutputProviderByEntityType(string $entityType): bool|string
    {
        foreach (self::getAllOutputProviders() as $outputProvider) {
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
     * @param int|string $entityId
     * @param string $entityType
     * @param OutputProviderInterface|null $OutputProvider (optional)
     * @param OutputTemplateProviderInterface|null $TemplateProvider (optional)
     * @param string|null $template (optional)
     * @param bool $preview (optional) - Get preview HTML
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDocumentHtml(
        int|string $entityId,
        string $entityType,
        OutputProviderInterface $OutputProvider = null,
        OutputTemplateProviderInterface $TemplateProvider = null,
        string $template = null,
        bool $preview = false
    ): string {
        if (empty($OutputProvider)) {
            $OutputProvider = self::getOutputProviderByEntityType($entityType);
        }

        if (empty($OutputProvider)) {
            throw new QUI\Exception('No output provider found for entity type "' . $entityType . '"');
        }

        if (empty($TemplateProvider)) {
            $TemplateProvider = self::getDefaultOutputTemplateProviderForEntityType($entityType);
        }

        if (empty($TemplateProvider)) {
            throw new QUI\Exception('No default output template provider found for entity type "' . $entityType . '"');
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
     * @param int|string $entityId
     * @param string $entityType
     * @param OutputProviderInterface|null $OutputProvider (optional)
     * @param OutputTemplateProviderInterface|null $TemplateProvider (optional)
     * @param string|null $template (optional)
     *
     * @return QUI\HtmlToPdf\Document
     *
     * @throws QUI\Exception
     */
    public static function getDocumentPdf(
        int|string $entityId,
        string $entityType,
        OutputProviderInterface $OutputProvider = null,
        OutputTemplateProviderInterface $TemplateProvider = null,
        string $template = null
    ): QUI\HtmlToPdf\Document {
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
     * @param int|string $entityId
     * @param string $entityType
     *
     * @return string
     */
    public static function getDocumentPdfDownloadUrl(int|string $entityId, string $entityType): string
    {
        $url = URL_OPT_DIR . 'quiqqer/erp/bin/output/frontend/download.php?';
        $url .= http_build_query([
            'id' => $entityId,
            't' => $entityType
        ]);

        return $url;
    }

    /**
     * Send document as e-mail with PDF attachment
     *
     * @param int|string $entityId
     * @param string $entityType
     * @param OutputProviderInterface|null $OutputProvider (optional)
     * @param OutputTemplateProviderInterface|null $TemplateProvider (optional)
     * @param string|null $template (optional)
     * @param string|null $recipientEmail (optional)
     * @param string|null $mailSubject (optional)
     * @param string|null $mailContent (optional)
     * @param <QUI\Projects\Media\File|QUI\Projects\Media\Image>[] $attachedMediaFiles (optional)
     *
     * @return void
     *
     * @throws QUI\Exception|\PHPMailer\PHPMailer\Exception
     */
    public static function sendPdfViaMail(
        int|string $entityId,
        string $entityType,
        OutputProviderInterface $OutputProvider = null,
        OutputTemplateProviderInterface $TemplateProvider = null,
        string $template = null,
        string $recipientEmail = null,
        string $mailSubject = null,
        string $mailContent = null,
        array $attachedMediaFiles = []
    ): void {
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
        $pdfDir = QUI::getPackage('quiqqer/erp')->getVarDir();
        $mailFile = $pdfDir . $OutputProvider::getDownloadFileName($entityId) . '.pdf';
        rename($pdfFile, $mailFile);

        if (!QUI\Utils\Security\Orthos::checkMailSyntax($recipientEmail)) {
            throw new QUI\ERP\Exception([
                'quiqqer/erp',
                'exception.Output.sendPdfViaMail.recpient_address_invalid'
            ]);
        }

        if (empty($recipientEmail)) {
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

        // Additional attachments
        foreach ($attachedMediaFiles as $MediaFile) {
            if (
                !($MediaFile instanceof QUI\Projects\Media\File) &&
                !($MediaFile instanceof QUI\Projects\Media\Image)
            ) {
                continue;
            }

            $Mailer->addAttachment($MediaFile->getFullPath());
        }

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

        QUI::getEvents()->fireEvent(
            'quiqqerErpOutputSendMailBefore',
            [$entityId, $entityType, $recipientEmail, $Mailer]
        );

        $Mailer->send();

        QUI::getEvents()->fireEvent('quiqqerErpOutputSendMail', [$entityId, $entityType, $recipientEmail]);

        // Delete PDF file after send
        if (file_exists($mailFile)) {
            unlink($mailFile);
        }
    }

    /**
     * Get available templates for $entityType (e.g. "Invoice", "InvoiceTemporary" etc.)
     *
     * @param string $package
     * @return OutputTemplateProviderInterface|false - OutputProvider class (static) or false if
     */
    public static function getOutputTemplateProviderByPackage(string $package): OutputTemplateProviderInterface|bool
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
     * @param string|null $entityType (optional) - Restrict to templates of $entityType [default: fetch templates for all entity types]
     * @return array
     */
    public static function getTemplates(string $entityType = null): array
    {
        $templates = [];
        $outputProviders = [];

        if (empty($entityType)) {
            $outputProviders = array_column(self::getAllOutputProviders(), 'class');
        } else {
            $OutputProvider = self::getOutputProviderByEntityType($entityType);

            if ($OutputProvider) {
                $outputProviders[] = $OutputProvider;
            }
        }

        foreach (self::getAllOutputTemplateProviders() as $provider) {
            /** @var OutputTemplateProviderInterface $class */
            $class = $provider['class'];
            $package = $provider['package'];

            /** @var OutputProviderInterface $OutputProvider */
            foreach ($outputProviders as $OutputProvider) {
                $entityType = $OutputProvider::getEntityType();
                $defaultOutputTemplate = self::getDefaultOutputTemplateForEntityType($entityType);

                foreach ($class::getTemplates($entityType) as $providerTemplateId) {
                    $templateTitle = $class::getTemplateTitle($providerTemplateId);

                    if ($provider['isSystemDefault']) {
                        $templateTitle .= ' ' . QUI::getLocale()->get('quiqqer/erp', 'output.default_template.suffix');
                    }

                    $isDefault = isset($defaultOutputTemplate['provider']) &&
                        $defaultOutputTemplate['provider'] === $provider['package'] &&
                        $defaultOutputTemplate['id'] === $providerTemplateId;

                    $providerTemplate = [
                        'id' => $providerTemplateId,
                        'title' => $templateTitle,
                        'provider' => $package,
                        'isSystemDefault' => $provider['isSystemDefault'],
                        'isDefault' => $isDefault,
                        'entityType' => $entityType,
                        'entityTypeTitle' => $OutputProvider::getEntityTypeTitle()
                    ];

                    $templates[] = $providerTemplate;
                }
            }
        }

        // Sort so that system default is first
        usort($templates, function ($a, $b) {
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
     * @return array - Containing template ID and template provider package
     */
    public static function getDefaultOutputTemplateForEntityType(string $entityType): array
    {
        $fallBackTemplate = [
            'id' => 'system_default',
            'provider' => 'quiqqer/erp-accounting-templates',
            'hideSystemDefault' => false
        ];

        try {
            $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
            $defaultTemplates = $Conf->get('output', 'default_templates');

            if (empty($defaultTemplates)) {
                return $fallBackTemplate;
            }

            $defaultTemplates = json_decode($defaultTemplates, true);

            if (empty($defaultTemplates[$entityType])) {
                return $fallBackTemplate;
            }

            return $defaultTemplates[$entityType];
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return $fallBackTemplate;
        }
    }

    /**
     * Return default Output Template provider class for a specific entity type
     *
     * @param string $entityType
     * @return string|false
     */
    public static function getDefaultOutputTemplateProviderForEntityType(string $entityType): string|bool
    {
        $defaultEntityTypeTemplate = self::getDefaultOutputTemplateForEntityType($entityType);

        foreach (self::getAllOutputTemplateProviders() as $provider) {
            if (isset($defaultEntityTypeTemplate['provider']) && $provider['package'] === $defaultEntityTypeTemplate['provider']) {
                return $provider['class'];
            }
        }

        // Fallback: Choose next available provider
        foreach (self::getAllOutputTemplateProviders() as $provider) {
            $class = $provider['class'];
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
    protected static function getAllOutputProviders(): array
    {
        $packages = QUI::getPackageManager()->getInstalled();
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

                foreach ($packageProvider['erpOutput'] as $class) {
                    if (!class_exists($class)) {
                        continue;
                    }

                    $providerClasses[] = [
                        'class' => $class,
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
    protected static function getAllOutputTemplateProviders(): array
    {
        $packages = QUI::getPackageManager()->getInstalled();
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

                foreach ($packageProvider['erpOutputTemplate'] as $class) {
                    if (!class_exists($class)) {
                        continue;
                    }

                    $providerClasses[] = [
                        'class' => $class,
                        'package' => $installedPackage['name'],
                        'isSystemDefault' => $installedPackage['name'] === 'quiqqer/erp-accounting-templates'
                    ];
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // Sort providers that system default is first
        usort($providerClasses, function ($a, $b) {
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
