<?php

namespace QUITests\ERP;

use QUI\ERP\Output\OutputTemplateProviderInterface;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Locale;

class AjaxOutputTemplateProviderFixture implements OutputTemplateProviderInterface
{
    public static function getEntityTypes(): array
    {
        return ['ajax-document'];
    }

    public static function getTemplates(string $entityType): array
    {
        return $entityType === 'ajax-document' ? ['ajax-layout'] : [];
    }

    public static function getTemplateTitle(int|string $templateId, ?Locale $Locale = null): string
    {
        return 'Ajax layout';
    }

    public static function getHeaderHtml(
        int|string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool|string {
        return '<header>' . $Entity->getId() . '</header>';
    }

    public static function getBodyHtml(
        int|string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool|string {
        return '<main>' . $Entity->getId() . '</main>';
    }

    public static function getFooterHtml(
        int|string $templateId,
        string $entityType,
        EngineInterface $Engine,
        mixed $Entity
    ): bool|string {
        return '<footer>' . $Entity->getId() . '</footer>';
    }
}
