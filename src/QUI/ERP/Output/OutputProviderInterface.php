<?php

namespace QUI\ERP\Output;

use QUI\HtmlToPdf\Document;

/**
 * Interface OutputProviderInterface
 *
 * Main interface for all ERP Output providers
 */
interface OutputProviderInterface
{
    /**
     * Get preview HTML of an entity output
     *
     * @param string|int $entityId
     * @param string $template
     * @return string - Preview HTML
     */
    public static function getPreview($entityId, string $template);

    /**
     * Get PDF Document of an entity output
     *
     * @param string|int $entityId
     * @param string $template
     * @return Document
     */
    public static function getPDFDocument($entityId, string $template);

    /**
     * Get e-mail address of the document recipient
     *
     * @param string|int $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     */
    public static function getEmailAddress($entityId);
}
