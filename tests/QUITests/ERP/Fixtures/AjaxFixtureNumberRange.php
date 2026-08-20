<?php

namespace QUITests\ERP;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;

class AjaxFixtureNumberRange implements NumberRangeInterface
{
    public static ?int $lastSetRange = null;
    private int $range = 700;

    public function getTitle(?QUI\Locale $Locale = null): string
    {
        return 'Fixture range';
    }

    public function getRange(): int
    {
        return $this->range;
    }

    public function setRange(int $range): void
    {
        $this->range = $range;
        self::$lastSetRange = $range;
    }
}
