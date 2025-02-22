<?php

namespace QUI\ERP\Output;

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
    public static function getEntityTypes(): array;

    /**
     * Get all available templates for $entityType
     *
     * @param string $entityType
     * @return string[]|int[] - Collection of templateIds
     */
    public static function getTemplates(string $entityType): array;

    /**
     * Get title of Template
     *
     * @param int|string $templateId
     * @param Locale|null $Locale $Locale (optional) - If omitted use \QUI::getLocale()
     * @return string
     */
    public static function getTemplateTitle(
        int | string $templateId,
        null | Locale $Locale = null
    ): string;

    /**
     * Get HTML for document header area
     *
     * @param int|string $templateId
     * @param string $entityType
     * @param EngineInterface $Engine
     * @param mixed $Entity - The entity the output is created for
     * @return string|false
     */
    public static function getHeaderHtml(
        int | string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool | string;

    /**
     * Get HTML for document body area
     *
     * @param int|string $templateId
     * @param string $entityType
     * @param EngineInterface $Engine
     * @param mixed $Entity - The entity the output is created for
     * @return string|false
     */
    public static function getBodyHtml(
        int | string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool | string;

    /**
     * Get HTML for document footer area
     *
     * @param int|string $templateId
     * @param string $entityType
     * @param EngineInterface $Engine
     * @param mixed $Entity - The entity the output is created for
     * @return string|false
     */
    public static function getFooterHtml(
        int | string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool | string;
}
