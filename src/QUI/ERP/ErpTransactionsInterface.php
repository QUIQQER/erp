<?php

namespace QUI\ERP;

use QUI\ERP\Accounting\Payments\Transactions\Transaction;

/**
 * The ErpTransactionsInterface is a PHP interface that specifies
 * that an ERP entity is able to receive and process transactions.
 *
 * The implementation of this interface in a class indicates that the instances
 * of this class are suitable for transactions, which is typically required for financial
 * and accounting processes within the ERP system.
 */
interface ErpTransactionsInterface
{
    public function linkTransaction(Transaction $Transaction): void;

    public function addTransaction(Transaction $Transaction): void;
}
