<?php

namespace QUITests\ERP\Output;

use QUI\ERP\Output\OutputProviderInterface;
use QUI\Interfaces\Users\User;
use QUI\Locale;

class OutputProviderFixture implements OutputProviderInterface
{
    public static Locale $Locale;

    public static function getEntityType(): string
    {
        return 'test-document';
    }

    public static function getEntityTypeTitle(?Locale $Locale = null): string|array
    {
        return 'Test document';
    }

    public static function getEntity(int|string $entityId): mixed
    {
        return ['id' => (int)$entityId, 'kind' => 'document'];
    }

    public static function getDownloadFileName(int|string $entityId): string
    {
        return 'document-' . $entityId;
    }

    public static function getLocale(int|string $entityId): Locale
    {
        return self::$Locale;
    }

    public static function getTemplateData(int|string $entityId): array
    {
        return ['documentId' => (int)$entityId];
    }

    public static function hasDownloadPermission(int|string $entityId, User $User): bool
    {
        return true;
    }

    public static function getEmailAddress(int|string $entityId): bool|string
    {
        return 'recipient@example.test';
    }

    public static function getMailSubject(int|string $entityId): string
    {
        return 'Document ' . $entityId;
    }

    public static function getMailBody(int|string $entityId): string
    {
        return 'Body ' . $entityId;
    }
}
