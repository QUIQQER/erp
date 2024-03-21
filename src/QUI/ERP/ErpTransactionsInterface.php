<?php

namespace QUI\ERP;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Address as ErpAddress;
use QUI\ERP\User as ErpUser;
use QUI\Interfaces\Users\User;

interface ErpTransactionsInterface
{
    public function linkTransaction(Transaction $Transaction): void;

    public function addTransaction(Transaction $Transaction): void;
}