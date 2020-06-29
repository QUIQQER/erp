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
     * Template constructor.
     *
     * @param string $TemplateProvider - Template provider class
     * @param string $entityType
     * @param string $template (optional) - Template identifier (from template provider)
     *
     * @throws QUI\Exception
     */
    public function __construct(
        string $TemplateProvider,
        string $entityType,
        string $template = null
    ) {
        $this->Engine           = QUI::getTemplateManager()->getEngine();
        $this->TemplateProvider = $TemplateProvider;

        if (empty($template)) {
            $templates = $this->TemplateProvider::getTemplates($entityType);
            $template  = $templates[0];
        }

        $this->template = $template;

        $this->entityType = $entityType;
    }

    /**
     * Render the html
     *
     * @return string
     */
    public function render()
    {
        return $this->getHTMLHeader().
               $this->getHTMLBody().
               $this->getHTMLFooter();
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
        return $this->TemplateProvider::getBodyHtml($this->template, $this->entityType, $this->Engine);
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
