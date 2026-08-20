<?php

namespace QUI\ERP\SalesOrders;

class Handler
{
    public static string $salesOrdersTable = 'processes_sales_orders_test';
    public static string $salesOrderDraftsTable = 'processes_sales_order_drafts_test';

    public static function getTableSalesOrders(): string
    {
        return self::$salesOrdersTable;
    }

    public static function getTableSalesOrderDrafts(): string
    {
        return self::$salesOrderDraftsTable;
    }

    public static function getSalesOrder(int | string $id): SalesOrder
    {
        throw new \QUI\Exception('Sales order not found');
    }

    public static function getSalesOrderByHash(string $hash): SalesOrder
    {
        throw new \QUI\Exception('Sales order not found');
    }
}
