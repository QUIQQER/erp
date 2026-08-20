<?php

namespace QUI\ERP\Accounting\Invoice;

abstract class Invoice implements
    \QUI\ERP\ErpEntityInterface,
    \QUI\ERP\ErpTransactionsInterface,
    \QUI\ERP\ErpCopyInterface
{
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

    public function getProcessingStatus(): mixed
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

    /** @param array<mixed> $files */
    public function setCustomFiles(array $files = []): void
    {
    }
}
