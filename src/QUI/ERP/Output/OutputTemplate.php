<?php

namespace QUI\ERP\Output;

use QUI;
use QUI\Package\Package;

use QUI\ERP\Accounting\Invoice\Exception;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;

/**
 * Class OutputTemplate
 */
class OutputTemplate
{
    /**
     * @var OutputTemplateProviderInterface
     */
    protected $TemplateProvider;

    /**
     * @var OutputProviderInterface
     */
    protected $OutputProvider;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var QUI\Interfaces\Template\EngineInterface
     */
    protected $Engine;

    /**
     * @var string
     */
    protected $entityType;

    /**
     * @var string|int
     */
    protected $entityId;

    /**
     * Template constructor.
     *
     * @param string $TemplateProvider - Template provider class
     * @param string $OutputProvider - Output provider class
     * @param string|int $entityId
     * @param string $entityType
     * @param string $template (optional) - Template identifier (from template provider)
     *
     * @throws QUI\Exception
     */
    public function __construct(
        string $TemplateProvider,
        string $OutputProvider,
        $entityId,
        string $entityType,
        string $template = null
    ) {
        $this->Engine           = QUI::getTemplateManager()->getEngine();
        $this->TemplateProvider = $TemplateProvider;
        $this->OutputProvider   = $OutputProvider;

        $templates = $this->TemplateProvider::getTemplates($entityType);

        if (empty($template)) {
            $template = $templates[0]['id'];
        } else {
            // Check if $template is provided by template provider
            $templateIsProvided = false;

            foreach ($templates as $providerTemplate) {
                if ($providerTemplate['id'] === $template) {
                    $templateIsProvided = true;
                    break;
                }
            }

            if (!$templateIsProvided) {
                $template = $templates[0]['id'];
            }
        }

        $this->template   = $template;
        $this->entityType = $entityType;
        $this->entityId   = $entityId;
    }

    /**
     * Render the html
     *
     * @return string - HTML content
     */
    public function getHTML()
    {
        $templateData = $this->OutputProvider::getTemplateData($this->entityId);
        $this->Engine->assign($templateData);

        return $this->getHTMLHeader().
               $this->getHTMLBody().
               $this->getHTMLFooter();
    }

    /**
     * Get PDF output
     *
     * @return QUI\HtmlToPdf\Document
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function getPDFDocument()
    {
        $Locale = $this->OutputProvider::getLocale($this->entityId);

        $Document = new QUI\HtmlToPdf\Document([
            'marginTop'         => 30, // dies ist variabel durch quiqqerInvoicePdfCreate
            'filename'          => $this->OutputProvider::getDownloadFileName($this->entityId).'.pdf',
            'marginBottom'      => 80,  // dies ist variabel durch quiqqerInvoicePdfCreate,
            'pageNumbersPrefix' => $Locale->get('quiqqer/htmltopdf', 'footer.page.prefix')
        ]);

        QUI::getEvents()->fireEvent(
            'quiqqerErpOutputPdfCreate',
            [$this, $Document]
        );

        try {
            $templateData = $this->OutputProvider::getTemplateData($this->entityId);
            $this->Engine->assign($templateData);

            $Document->setHeaderHTML($this->getHTMLHeader());
            $Document->setContentHTML($this->getHTMLBody());
            $Document->setFooterHTML($this->getHTMLFooter());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return $Document;
    }

    /**
     * @return QUI\Interfaces\Template\EngineInterface
     */
    public function getEngine()
    {
        return $this->Engine;
    }

    //region Template Output Helper

    /**
     * Return the html header
     *
     * @return string
     */
    public function getHTMLHeader()
    {
        return $this->TemplateProvider::getHeaderHtml($this->template, $this->entityType, $this->Engine);
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLBody()
    {
        $Output = new QUI\Output();
        $Output->setSetting('use-system-image-paths', true);

        return $Output->parse($this->TemplateProvider::getBodyHtml($this->template, $this->entityType, $this->Engine));
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLFooter()
    {
        return $this->TemplateProvider::getFooterHtml($this->template, $this->entityType, $this->Engine);
    }

    //endregion
}
