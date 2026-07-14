<?php

namespace QUI\ERP\Accounting\Invoice;

class Handler
{
    public static function getInstance(): self
    {
    }

    public function invoiceTable(): string
    {
    }

    /** @return list<Invoice|InvoiceTemporary> */
    public function getInvoicesByGlobalProcessId(int | string $processId): array
    {
    }

    public function getInvoiceByHash(string $hash): Invoice | InvoiceTemporary
    {
    }
}
