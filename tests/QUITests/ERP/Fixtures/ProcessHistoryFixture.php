<?php

namespace QUITests\ERP;

use Doctrine\DBAL\Connection;
use QUI\ERP\Process;

class ProcessHistoryFixture extends Process
{
    /** @var array<mixed> */
    public array $invoices = [];
    /** @var array<mixed> */
    public array $orders = [];
    /** @var array<mixed> */
    public array $offers = [];
    /** @var array<mixed> */
    public array $bookings = [];
    /** @var array<mixed> */
    public array $purchasing = [];
    /** @var array<mixed> */
    public array $salesOrders = [];
    /** @var array<mixed> */
    public array $transactionFixtures = [];

    public function __construct(
        string $processId,
        private Connection $Connection,
        private string $historyTable
    ) {
        parent::__construct($processId);
    }

    protected function getDatabaseConnection(): Connection
    {
        return $this->Connection;
    }

    protected function table(): string
    {
        return $this->historyTable;
    }

    public function getInvoices(): array
    {
        return $this->invoices;
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function getOffers(): array
    {
        return $this->offers;
    }

    public function getBookings(): array
    {
        return $this->bookings;
    }

    public function getPurchasing(): array
    {
        return $this->purchasing;
    }

    public function getSalesOrders(): array
    {
        return $this->salesOrders;
    }

    public function getTransactions(): array
    {
        return $this->transactionFixtures;
    }
}
