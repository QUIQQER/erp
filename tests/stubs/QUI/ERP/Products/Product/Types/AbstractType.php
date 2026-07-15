<?php

namespace QUI\ERP\Products\Product\Types;

class AbstractType
{
    public function createUniqueProduct(
        ?\QUI\Interfaces\Users\User $User = null
    ): \QUI\ERP\Products\Product\UniqueProduct {
        return new \QUI\ERP\Products\Product\UniqueProduct();
    }

    public function getField(int | string $fieldId): \QUI\ERP\Products\Field\Field
    {
        return new \QUI\ERP\Products\Field\Field();
    }

    public function getImage(): \QUI\Projects\Media\Image
    {
        return new \QUI\Projects\Media\Image();
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return [];
    }
}
