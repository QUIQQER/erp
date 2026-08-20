<?php

namespace QUITests\ERP\Output;

class ProjectAwareOutputProviderFixture extends OutputProviderFixture
{
    public static function getEntity(int|string $entityId): mixed
    {
        return new ProjectAwareEntityFixture();
    }
}
