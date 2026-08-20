<?php

namespace QUI\ERP\Accounting\Invoice;

class Handler extends \QUI\Utils\Singleton
{
    /** @var array<string, list<Invoice|InvoiceTemporary>> */
    public static array $invoicesByProcessId = [];

    public function invoiceTable(): string
    {
        return 'processes_invoice_test';
    }

    /** @return list<Invoice|InvoiceTemporary> */
    public function getInvoicesByGlobalProcessId(int | string $processId): array
    {
        return self::$invoicesByProcessId[(string)$processId] ?? [];
    }

    public function getInvoiceByHash(string $hash): Invoice | InvoiceTemporary
    {
        throw new \QUI\Exception('Invoice not found');
    }
}
