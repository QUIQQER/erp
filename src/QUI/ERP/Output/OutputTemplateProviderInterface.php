<?php

namespace QUI\ERP\Output;

use QUI\HtmlToPdf\Document;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Locale;

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
     * @return string[]|int[] - Collection of templateIds
     */
    public static function getTemplates(string $entityType);

    /**
     * Get title of Template
     *
     * @param string|int $templateId
     * @param Locale $Locale (optional) - If omitted use \QUI::getLocale()
     * @return string
     */
    public static function getTemplateTitle($templateId, Locale $Locale = null);

    /**
     * Get HTML for document header area
     *
     * @param string|int $templateId
     * @param string $entityType
     * @param EngineInterface $Engine
     * @param mixed $Entity - The entity the output is created for
     * @return string|false
     */
    public static function getHeaderHtml($templateId, string $entityType, EngineInterface $Engine, $Entity);

    /**
     * Get HTML for document body area
     *
     * @param string|int $templateId
     * @param string $entityType
     * @param EngineInterface $Engine
     * @param mixed $Entity - The entity the output is created for
     * @return string|false
     */
    public static function getBodyHtml($templateId, string $entityType, EngineInterface $Engine, $Entity);

    /**
     * Get HTML for document footer area
     *
     * @param string|int $templateId
     * @param string $entityType
     * @param EngineInterface $Engine
     * @param mixed $Entity - The entity the output is created for
     * @return string|false
     */
    public static function getFooterHtml($templateId, string $entityType, EngineInterface $Engine, $Entity);
}
