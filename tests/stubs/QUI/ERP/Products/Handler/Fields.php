<?php

namespace QUI\ERP\Products\Handler;

class Fields
{
    public const FIELD_VAT = 2;
    public const FIELD_SHORT_DESC = 5;
    public const FIELD_MANUFACTURER = 8;
    public const FIELD_UNIT = 20;

    public static function getField(int $fieldId): \QUI\ERP\Products\Field\Field
    {
        return new \QUI\ERP\Products\Field\Field();
    }
}
