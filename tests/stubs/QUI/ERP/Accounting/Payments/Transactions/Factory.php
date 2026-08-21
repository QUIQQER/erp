<?php

namespace QUI\ERP\Accounting\Payments\Transactions;

class Factory
{
    public static string $transactionsTable = 'processes_transactions_test';

    public static function table(): string
    {
        return self::$transactionsTable;
    }
}
