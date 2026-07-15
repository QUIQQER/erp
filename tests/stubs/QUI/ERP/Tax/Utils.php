<?php

namespace QUI\ERP\Tax;

class Utils
{
    public static function getTaxTypeByArea(\QUI\ERP\Areas\Area $Area): TaxType
    {
        return new TaxType(0);
    }

    public static function getTaxEntry(TaxType $TaxType, \QUI\ERP\Areas\Area $Area): TaxEntry
    {
        return new TaxEntry();
    }

    public static function getTaxByUser(\QUI\Interfaces\Users\User $User): TaxEntry
    {
        return new TaxEntry();
    }

    public static function isUserEuVatUser(\QUI\Interfaces\Users\User $User): bool
    {
        return false;
    }

    public static function validateVatId(string $vatId): string
    {
        return $vatId;
    }

    public static function cleanupVatId(string $vatId): string
    {
        return $vatId;
    }

    public static function shouldVatIdValidationBeExecuted(): bool
    {
        return false;
    }

    public static function cleanUpUserTaxCache(\QUI\Interfaces\Users\User $User): void
    {
    }
}
