<?php

namespace QUI\ERP\Output;

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
    public static function getEntityType(): string;

    /**
     * Get title for the output entity
     *
     * @param Locale|null $Locale $Locale (optional) - If omitted use \QUI::getLocale()
     * @return mixed
     */
    public static function getEntityTypeTitle(null | Locale $Locale = null): mixed;

    /**
     * Get the entity the output is created for
     *
     * @param int|string $entityId
     * @return mixed
     */
    public static function getEntity(int | string $entityId): mixed;

    /**
     * Get download filename (without file extension)
     *
     * @param int|string $entityId
     * @return string
     */
    public static function getDownloadFileName(int | string $entityId): string;

    /**
     * Get output Locale by entity
     *
     * @param int|string $entityId
     * @return Locale
     */
    public static function getLocale(int | string $entityId): Locale;

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param int|string $entityId
     * @return array
     */
    public static function getTemplateData(int | string $entityId): array;

    /**
     * Checks if $User has permission to download the document of $entityId
     *
     * @param int|string $entityId
     * @param User $User
     * @return bool
     */
    public static function hasDownloadPermission(int | string $entityId, User $User): bool;

    /**
     * Get e-mail address of the document recipient
     *
     * @param int|string $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     */
    public static function getEmailAddress(int | string $entityId): bool | string;

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     */
    public static function getMailSubject(int | string $entityId): string;

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     */
    public static function getMailBody(int | string $entityId): string;
}
