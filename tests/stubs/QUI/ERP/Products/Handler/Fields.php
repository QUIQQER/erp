<?php

namespace QUI\ERP\Products\Handler;

class Fields
{
    public const FIELD_VAT = 2;
    public const FIELD_SHORT_DESC = 5;
    public const FIELD_MANUFACTURER = 8;
    public const FIELD_UNIT = 20;

    /** @var array<int, \QUI\ERP\Products\Field\Field> */
    private static array $list = [];

    public static function getField(int $fieldId): \QUI\ERP\Products\Field\Field
    {
        if (isset(self::$list[$fieldId])) {
            return clone self::$list[$fieldId];
        }

        return new \QUI\ERP\Products\Field\Field();
    }
}
