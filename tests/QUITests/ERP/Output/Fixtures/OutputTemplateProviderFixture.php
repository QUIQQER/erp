<?php

namespace QUITests\ERP\Output;

use QUI\ERP\Output\OutputTemplateProviderInterface;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Locale;

class OutputTemplateProviderFixture implements OutputTemplateProviderInterface
{
    public static function getEntityTypes(): array
    {
        return ['test-document'];
    }

    public static function getTemplates(string $entityType): array
    {
        return $entityType === 'test-document' ? ['modern'] : [];
    }

    public static function getTemplateTitle(int|string $templateId, ?Locale $Locale = null): string
    {
        return 'Modern template';
    }

    public static function getHeaderHtml(
        int|string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool|string {
        return '<header>' . $Entity['id'] . '</header>';
    }

    public static function getBodyHtml(
        int|string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool|string {
        return '<main>' . $Entity['id'] . '</main>';
    }

    public static function getFooterHtml(
        int|string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool|string {
        return '<footer>' . $Entity['id'] . '</footer>';
    }
}
