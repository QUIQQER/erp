<?php

namespace QUI\ERP\Products\Utils;

class PriceFactor
{
    public function getNettoSum(): float | int
    {
        return 0;
    }

    public function getVat(): float | int | false
    {
        return false;
    }

    public function setVat(float | int $vat): void
    {
    }

    public function setSum(float | int $sum): void
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }
}
