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
     * Get output type
     *
     * The output type determines the type of templates/providers that are used
     * to output documents.
     *
     * @return string
     */
    public static function getOutputType();

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param string|int $entityId
     * @param OutputTemplate $Template
     * @return void
     */
    public static function parseTemplate($entityId, OutputTemplate $Template);

    /**
     * Get e-mail address of the document recipient
     *
     * @param string|int $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     */
    public static function getEmailAddress($entityId);
}
