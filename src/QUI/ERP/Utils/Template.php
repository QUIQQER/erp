<?php

/**
 * This file contains QUI\ERP\Utils\Templates
 */

namespace QUI\ERP\Utils;

use QUI;
use QUI\Package\Package;

/**
 * Class Template
 *
 * @package QUI\ERP\Utils
 */
class Template
{
    const TYPE_INVOICE = 1;
    const TYPE_CREDIT_NOTE = 2;

    /**
     * invoice | creditNode
     *
     * @var string
     */
    protected $type;

    /**
     * @var Package
     */
    protected $Template;

    /**
     * @var QUI\Interfaces\Template\EngineInterface
     */
    protected $Engine;

    /**
     * Template constructor.
     *
     * @param Package $Template
     * @param $type
     *
     * @throws QUI\ERP\Exception
     */
    public function __construct(Package $Template, $type)
    {
        switch ($type) {
            case self::TYPE_INVOICE:
                $this->type = 'Invoice';
                break;

            case self::TYPE_CREDIT_NOTE:
                $this->type = 'CreditNote';
                break;

            default:
                throw new QUI\ERP\Exception('Unknown Template Type');
        }

        $this->Engine   = QUI::getTemplateManager()->getEngine();
        $this->Template = $Template;
    }

    /**
     * Render the html
     *
     * @return string
     */
    public function render()
    {
        return $this->getHTMLHeader() .
               $this->getHTMLBody() .
               $this->getHTMLFooter();
    }

    /**
     * Render the preview html
     *
     * @return string
     */
    public function renderPreview()
    {
        $output = '';
        $output .= '<style>';
        $output .= file_get_contents(dirname(__FILE__) . '/Template.Preview.css');
        $output .= '</style>';
        $output .= $this->render();

        return $output;
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
        return $this->getTemplate('header');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLBody()
    {
        return $this->getTemplate('body');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLFooter()
    {
        return $this->getTemplate('footer');
    }

    /**
     * Helper for template check
     *
     * @param $template
     * @return string
     */
    protected function getTemplate($template)
    {
        // main folder
        $htmlFile = $this->getFile($template . '.html');
        $cssFile  = $this->getFile($template . '.css');

        $Output = new QUI\Output();
        $Output->setSetting('use-system-image-paths', true);

        $output = '';

        if (file_exists($cssFile)) {
            $output .= '<style>' . file_get_contents($cssFile) . '</style>';
        }

        if (file_exists($htmlFile)) {
            $output .= $this->getEngine()->fetch($htmlFile);
        }

        return $Output->parse($output);
    }

    /**
     * Return file
     * Checks some paths
     *
     * @param $wanted
     * @return string
     * @throws QUI\ERP\Exception
     */
    public function getFile($wanted)
    {
        $package = $this->Template->getName();
        $usrPath = USR_DIR . $package . '/template/';

        if (file_exists($usrPath . $this->type . '/' . $wanted)) {
            return $usrPath . $this->type . '/' . $wanted;
        }

        if (file_exists($usrPath . $wanted)) {
            return $usrPath . $wanted;
        }


        $optPath = OPT_DIR . $package . '/template/';

        if (file_exists($optPath . $this->type . '/' . $wanted)) {
            return $optPath . $this->type . '/' . $wanted;
        }

        if (file_exists($optPath . $wanted)) {
            return $optPath . $wanted;
        }


        QUI\System\Log::addWarning('File not found in ERP Template ' . $wanted);
        return '';
    }

    //endregion
}
