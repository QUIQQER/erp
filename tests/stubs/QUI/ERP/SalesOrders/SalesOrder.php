<?php

namespace QUI\ERP\SalesOrders;

abstract class SalesOrder implements \QUI\ERP\ErpEntityInterface, \QUI\ERP\ErpTransactionsInterface
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
