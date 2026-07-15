<?php

namespace QUI\ERP\Products\Interfaces;

interface PriceFactorWithVatInterface
{
    public function getCalculationBasis(): int;

    public function getSum(): float | int;

    public function setSum(float | int $sum): void;
}
