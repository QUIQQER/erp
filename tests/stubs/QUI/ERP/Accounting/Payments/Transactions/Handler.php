<?php

namespace QUI\ERP\Accounting\Payments\Transactions;

class Handler
{
    public static function getInstance(): self
    {
    }

    /** @return list<Transaction> */
    public function getTransactionsByHash(string $hash): array
    {
    }

    /** @return list<Transaction> */
    public function getTransactionsByProcessId(string $processId): array
    {
    }
}
