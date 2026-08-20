<?php

namespace QUITests\ERP;

class ProcessTransactionFixture
{
    public function __construct(
        private string $hash,
        private string $amount,
        private string $date
    ) {
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getAmountFormatted(): string
    {
        return $this->amount;
    }

    public function getDate(): string
    {
        return $this->date;
    }
}
