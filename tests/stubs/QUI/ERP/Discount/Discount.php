<?php

namespace QUI\ERP\Discount;

class Discount
{
    public function getId(): int
    {
        return 0;
    }

    public function canCombinedWith(self $Discount): bool
    {
        return true;
    }
}
