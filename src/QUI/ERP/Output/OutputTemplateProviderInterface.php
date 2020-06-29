<?php

namespace QUI\ERP\Output;

use QUI\HtmlToPdf\Document;
use QUI\Interfaces\Template\EngineInterface;

/**
 * Interface OutputTemplateProviderInterface
 *
 * Main interface for all ERP Output template providers
 */
interface OutputTemplateProviderInterface
{
    /**
     * Get all entity types the template package provides templates for
     *
     * @return string[]
     */
    public static function getEntityTypes();

    /**
     * Get all available templates for $entityType
     *
     * @param string $entityType
     * @return array
     */
    public static function getTemplates(string $entityType);

    /**
     * Get HTML for document header area
     *
     * @param string $template
     * @param string $entityType
     * @param EngineInterface $Engine
     * @return string|false
     */
    public static function getHeaderHtml(string $template, string $entityType, EngineInterface $Engine);

    /**
     * Get HTML for document body area
     *
     * @param string $template
     * @param string $entityType
     * @param EngineInterface $Engine
     * @return string|false
     */
    public static function getBodyHtml(string $template, string $entityType, EngineInterface $Engine);

    /**
     * Get HTML for document footer area
     *
     * @param string $template
     * @param string $entityType
     * @param EngineInterface $Engine
     * @return string|false
     */
    public static function getFooterHtml(string $template, string $entityType, EngineInterface $Engine);
}
