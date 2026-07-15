<?php

namespace QUI\ERP\Products\Product\Types;

class VariantParent extends AbstractType
{
    /** @return array<int, AbstractType>|int */
    public function getVariants(array $params = []): array | int
    {
        return [];
    }
}
