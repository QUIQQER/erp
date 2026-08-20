<?php

namespace QUITests\ERP\Output;

use QUI\Locale;

class SystemTemplateProviderFixture extends OutputTemplateProviderFixture
{
    public static function getTemplates(string $entityType): array
    {
        return $entityType === 'test-document' ? ['system_default'] : [];
    }

    public static function getTemplateTitle(int|string $templateId, ?Locale $Locale = null): string
    {
        return 'System template';
    }
}
