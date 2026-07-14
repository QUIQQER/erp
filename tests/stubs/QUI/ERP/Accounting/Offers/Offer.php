<?php

namespace QUI\ERP\Accounting\Offers;

abstract class Offer implements \QUI\ERP\ErpEntityInterface
{
    public function getHash(): string
    {
    }

    public function getAttribute(string $name): mixed
    {
    }

    public function getHistory(): \QUI\ERP\Comments
    {
    }
}
