<?php

namespace QUI\ERP;

use QUI\ERP\Accounting\Payments\Transactions\Transaction;

interface ErpTransactionsInterface
{
    public function linkTransaction(Transaction $Transaction): void;

    public function addTransaction(Transaction $Transaction): void;
}
