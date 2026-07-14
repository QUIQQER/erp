<?php

namespace QUI\ERP\Order;

abstract class AbstractOrder implements \QUI\ERP\ErpEntityInterface
{
    public function getDeliveryAddress(): \QUI\ERP\Address
    {
    }

    public function getAttribute(string $name): mixed
    {
    }

    public function getPrefixedNumber(): string
    {
    }

    public function getUUID(): string
    {
    }

    public function getHistory(): ?\QUI\ERP\Comments
    {
    }

    public function getCreateDate(): string
    {
    }

    /** @return array<string, mixed> */
    public function getPaymentDataEntry(string $key): array
    {
    }

    /** @return array<string, mixed> */
    public function getCustomDataEntry(string $key): array
    {
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
    }
}
