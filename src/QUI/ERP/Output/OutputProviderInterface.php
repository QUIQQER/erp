<?php

namespace QUI\ERP\Output;

use QUI\HtmlToPdf\Document;
use QUI\Interfaces\Users\User;
use QUI\Locale;

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
    public static function getEntityType();

    /**
     * Get download filename (without file extension)
     *
     * @param string|int $entityId
     * @return string
     */
    public static function getDownloadFileName($entityId);

    /**
     * Get output Locale by entity
     *
     * @param string|int $entityId
     * @return Locale
     */
    public static function getLocale($entityId);

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param string|int $entityId
     * @return array
     */
    public static function getTemplateData($entityId);

    /**
     * Checks if $User has permission to download the document of $entityId
     *
     * @param string|int $entityId
     * @param User $User
     * @return bool
     */
    public static function hasDownloadPermission($entityId, User $User);

    /**
     * Get e-mail address of the document recipient
     *
     * @param string|int $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     */
    public static function getEmailAddress($entityId);

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     */
    public static function getMailSubject($entityId);

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     */
    public static function getMailBody($entityId);
}
