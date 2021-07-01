<?php

namespace QUI\ERP\Output;

use QUI;

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
     * The entity the output is created for
     *
     * @var mixed
     */
    protected $Entity;

    /**
     * @var bool
     */
    protected $preview = false;

    /**
     * Template constructor.
     *
     * @param string|OutputTemplateProviderInterface $TemplateProvider - Template provider class
     * @param string|OutputProviderInterface $OutputProvider - Output provider class
     * @param string|int $entityId
     * @param string $entityType
     * @param string|null $template (optional) - Template identifier (from template provider)
     *
     * @throws QUI\Exception
     */
    public function __construct(
        $TemplateProvider,
        $OutputProvider,
        $entityId,
        string $entityType,
        $template = null
    ) {
        $this->Engine           = QUI::getTemplateManager()->getEngine();
        $this->TemplateProvider = $TemplateProvider;
        $this->OutputProvider   = $OutputProvider;

        $templates = $this->TemplateProvider::getTemplates($entityType);

        if (empty($template)) {
            $template = $templates[0];
        } else {
            // Check if $template is provided by template provider
            $templateIsProvided = false;

            foreach ($templates as $providerTemplateId) {
                if ($providerTemplateId === $template) {
                    $templateIsProvided = true;
                    break;
                }
            }

            if (!$templateIsProvided) {
                $template = $templates[0];
            }
        }

        $this->template   = $template;
        $this->entityType = $entityType;
        $this->entityId   = $entityId;
        $this->Entity     = $this->OutputProvider::getEntity($entityId);
    }

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->Entity;
    }

    /**
     * Render the html
     *
     * @param bool $preview (optional) -
     * @return string - HTML content
     */
    public function getHTML($preview = false): string
    {
        $Locale = $this->OutputProvider::getLocale($this->entityId);
        QUI::getLocale()->setTemporaryCurrent($Locale->getCurrent());

        $templateData                    = $this->OutputProvider::getTemplateData($this->entityId);
        $templateData['erpOutputEntity'] = $this->Entity;

        $this->Engine->assign($templateData);
        $this->preview = $preview;

        $html = '<style>
    body {
        display: flex;
        flex-direction: column;
        margin: 0;
        height: 100%;
    }
    
    .quiqqer-erp-output-html-footer {
        position: relative;
        margin-top: auto;
    }
    
    .quiqqer-erp-output-footer {
        position: static !important;
    }
</style>';

        $html .= '<div class="quiqqer-erp-output-html-header">'.$this->getHTMLHeader().'</div>';
        $html .= '<div class="quiqqer-erp-output-html-body">'.$this->getHTMLBody().'</div>';
        $html .= '<div class="quiqqer-erp-output-html-footer">'.$this->getHTMLFooter().'</div>';

        QUI::getLocale()->resetCurrent();

        return $html;
    }

    /**
     * Get PDF output
     *
     * @return QUI\HtmlToPdf\Document
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function getPDFDocument(): QUI\HtmlToPdf\Document
    {
        $Locale = $this->OutputProvider::getLocale($this->entityId);
        QUI::getLocale()->setTemporaryCurrent($Locale->getCurrent());

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
            \putenv('QUIQQER_CACHE_DISABLE_WEBP=1');

            $templateData = $this->OutputProvider::getTemplateData($this->entityId);
            $this->Engine->assign($templateData);

            $Document->setHeaderHTML($this->getHTMLHeader());
            $Document->setContentHTML($this->getHTMLBody());
            $Document->setFooterHTML($this->getHTMLFooter());

            \putenv('QUIQQER_CACHE_DISABLE_WEBP');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        QUI::getLocale()->resetCurrent();

        return $Document;
    }

    /**
     * @return QUI\Interfaces\Template\EngineInterface
     */
    public function getEngine(): QUI\Interfaces\Template\EngineInterface
    {
        return $this->Engine;
    }

    /**
     * @return OutputTemplateProviderInterface|string
     */
    public function getTemplateProvider()
    {
        if (\is_string($this->TemplateProvider)) {
            return $this->TemplateProvider;
        }

        return \get_class($this->TemplateProvider);
    }

    //region Template Output Helper

    /**
     * Return the html header
     *
     * @return string
     */
    public function getHTMLHeader(): string
    {
        return $this->TemplateProvider::getHeaderHtml($this->template, $this->entityType, $this->Engine, $this->Entity);
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLBody(): string
    {
        $Output = new QUI\Output();
        $Output->setSetting('use-system-image-paths', true);

        return $Output->parse(
            $this->TemplateProvider::getBodyHtml(
                $this->template,
                $this->entityType,
                $this->Engine,
                $this->Entity
            )
        );
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLFooter(): string
    {
        $footerHtml = '<div class="quiqqer-erp-output-footer">';

        $footerHtml .= $this->TemplateProvider::getFooterHtml(
            $this->template,
            $this->entityType,
            $this->Engine,
            $this->Entity
        );

        $footerHtml .= '</div>';

        $css = '';

        if ($this->preview) {
            $css = '<style>';
            $css .= '
            .quiqqer-erp-output-footer {
                position: absolute;
                left: 0;
                bottom: 0;
                width: 100%;
            }
            
            .quiqqer-erp-output-footer footer {
                position: static !important;            
            }
        ';
            $css .= '</style>';
        }

        return $css.$footerHtml;
    }

    //endregion
}
