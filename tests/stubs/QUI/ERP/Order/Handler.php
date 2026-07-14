<?php

namespace QUI\ERP\Order;

class Handler
{
    public static function getInstance(): self
    {
    }

    public function table(): string
    {
    }

    public function getOrderByHash(string $hash): Order | OrderInProcess
    {
    }

    public function getOrderByGlobalProcessId(int | string $processId): Order
    {
    }

    /** @return list<Order|OrderInProcess> */
    public function getOrdersByGlobalProcessId(string $processId): array
    {
    }

    public function get(int | string $id): Order | OrderInProcess
    {
    }
}
