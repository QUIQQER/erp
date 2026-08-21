<?php

namespace QUI\ERP\Order;

class Handler extends \QUI\Utils\Singleton
{
    public static string $orderTable = 'processes_order_test';

    public function table(): string
    {
        return self::$orderTable;
    }

    public function getOrderByHash(string $hash): Order | OrderInProcess
    {
        throw new \QUI\Exception('Order not found');
    }

    public function getOrderByGlobalProcessId(int | string $processId): Order
    {
        throw new \QUI\Exception('Order not found');
    }

    /** @return list<Order|OrderInProcess> */
    public function getOrdersByGlobalProcessId(string $processId): array
    {
        return [];
    }

    public function get(int | string $id): Order | OrderInProcess
    {
        throw new \QUI\Exception('Order not found');
    }
}
