<?php

namespace QUITests\ERP\Output;

class EmptyTemplateProviderFixture extends OutputTemplateProviderFixture
{
    public static function getTemplates(string $entityType): array
    {
        return [];
    }
}
