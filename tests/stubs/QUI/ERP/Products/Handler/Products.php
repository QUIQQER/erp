<?php

namespace QUI\ERP\Products\Handler;

class Products
{
    public static function getProduct(int $productId): \QUI\ERP\Products\Product\Types\AbstractType
    {
        return new \QUI\ERP\Products\Product\Types\AbstractType();
    }

    public static function getProductByProductNo(string $productNo): object
    {
        return new \stdClass();
    }
}
