<?php

namespace QUI\ERP\Accounting\Payments\Transactions;

class Transaction
{
    public function getHash(): string
    {
    }

    public function getAmountFormatted(): string
    {
    }

    public function getDate(): string
    {
    }

    public function isComplete(): bool
    {
    }

    public function getAmount(): float
    {
    }

    public function getCurrency(): \QUI\ERP\Currency\Currency
    {
    }

    public function getData(string $key): mixed
    {
    }

    public function getTxId(): string
    {
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
    }
}
