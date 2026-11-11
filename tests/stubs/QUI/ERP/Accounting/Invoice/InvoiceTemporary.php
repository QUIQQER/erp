<?php

namespace QUI\ERP\Accounting\Invoice;

abstract class InvoiceTemporary implements \QUI\ERP\ErpEntityInterface
{
    public function getHash(): string
    {
    }

    public function getCleanId(): int
    {
    }

    public function getPrefixedNumber(): string
    {
    }

    public function getAttribute(string $name): mixed
    {
    }

    public function getUUID(): string
    {
    }

    public function getHistory(): \QUI\ERP\Comments
    {
    }

    /** @return array<string, mixed> */
    public function getPaymentData(string $key): array
    {
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
    }
}
