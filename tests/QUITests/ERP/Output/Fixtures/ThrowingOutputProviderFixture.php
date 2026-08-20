<?php

namespace QUITests\ERP\Output;

use QUI;

class ThrowingOutputProviderFixture extends OutputProviderFixture
{
    public static function getEntity(int|string $entityId): mixed
    {
        throw new QUI\Exception('Entity unavailable');
    }
}
