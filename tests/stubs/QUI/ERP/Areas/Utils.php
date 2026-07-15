<?php

namespace QUI\ERP\Areas;

class Utils
{
    public static function getAreaByCountry(mixed $Country): ?Area
    {
        return new Area();
    }

    /** @param array<int, string> $areas */
    public static function isUserInAreas(\QUI\Interfaces\Users\User $User, array $areas = []): bool
    {
        return false;
    }
}
