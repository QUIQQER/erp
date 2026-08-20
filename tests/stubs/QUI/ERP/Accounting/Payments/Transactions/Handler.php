<?php

namespace QUI\ERP\Accounting\Payments\Transactions;

class Handler extends \QUI\Utils\Singleton
{
    /** @return list<Transaction> */
    public function getTransactionsByHash(string $hash): array
    {
        return [];
    }

    /** @return list<Transaction> */
    public function getTransactionsByProcessId(string $processId): array
    {
        return [];
    }
}
