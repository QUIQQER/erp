<?php

namespace QUITests\ERP;

use QUI;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Locale;

class AjaxOutputProviderFixture implements OutputProviderInterface
{
    public static function getEntityType(): string
    {
        return 'ajax-document';
    }

    public static function getEntityTypeTitle(?Locale $Locale = null): string|array
    {
        return 'Ajax document';
    }

    public static function getEntity(int|string $entityId): mixed
    {
        return new AjaxOutputEntityFixture((int)$entityId);
    }

    public static function getDownloadFileName(int|string $entityId): string
    {
        return 'ajax-document-' . $entityId;
    }

    public static function getLocale(int|string $entityId): Locale
    {
        return QUI::getLocale();
    }

    public static function getTemplateData(int|string $entityId): array
    {
        return ['documentId' => (int)$entityId];
    }

    public static function hasDownloadPermission(int|string $entityId, UserInterface $User): bool
    {
        return true;
    }

    public static function getEmailAddress(int|string $entityId): bool|string
    {
        return 'ajax-recipient@example.test';
    }

    public static function getMailSubject(int|string $entityId): string
    {
        return 'Ajax document ' . $entityId;
    }

    public static function getMailBody(int|string $entityId): string
    {
        return 'Mail body for ' . $entityId;
    }
}
