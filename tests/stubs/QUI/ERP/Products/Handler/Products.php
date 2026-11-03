<?php

namespace QUI\ERP\Products\Handler;

class Products
{
    /** @var array<int, \QUI\ERP\Products\Product\Types\AbstractType> */
    private static array $list = [];

    public static function getProduct(int $productId): \QUI\ERP\Products\Product\Types\AbstractType
    {
        return self::$list[$productId] ?? new \QUI\ERP\Products\Product\Types\AbstractType();
    }

    public static function getProductByProductNo(string $productNo): object
    {
        return new \stdClass();
    }
}
