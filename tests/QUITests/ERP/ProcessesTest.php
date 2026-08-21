<?php

namespace QUITests\ERP;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Offers\Handler as OffersHandler;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Processes;
use QUI\ERP\SalesOrders\Handler as SalesOrdersHandler;
use QUI\Exception;
use QUI\Package\Manager;
use ReflectionClass;

class ProcessesTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (
            [
            'QUI/ERP/Accounting/Invoice/Handler.php',
            'QUI/ERP/Accounting/Offers/Handler.php',
            'QUI/ERP/Order/Handler.php',
            'QUI/ERP/SalesOrders/Handler.php',
            'QUI/ERP/Accounting/Payments/Transactions/Factory.php'
            ] as $stubFile
        ) {
            $className = str_replace(['/', '.php'], ['\\', ''], $stubFile);

            if (!class_exists($className, false)) {
                require_once dirname(__DIR__, 2) . '/stubs/' . $stubFile;
            }
        }
    }

    public function testGetWantedPluginList(): void
    {
        $Processes = new Processes();
        $list = $Processes->getWantedPluginList();

        $this->assertContains('quiqqer/invoice', $list);
        $this->assertContains('quiqqer/order', $list);
        $this->assertContains('quiqqer/payment-transactions', $list);
        $this->assertCount(10, $list);
    }

    public function testGetEarlierDateHelper(): void
    {
        $Processes = new Processes();
        $Reflection = new ReflectionClass(Processes::class);
        $method = $Reflection->getMethod('getEarlierDate');

        $this->assertSame('2026-01-01', $method->invoke($Processes, null, '2026-01-01'));
        $this->assertSame('2026-01-01', $method->invoke($Processes, '2026-01-01', null));
        $this->assertSame('2025-12-31', $method->invoke($Processes, '2025-12-31', '2026-01-01'));
    }

    public function testGetEntityThrowsExceptionForUnknownPlugin(): void
    {
        $Processes = new Processes();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $Processes->getEntity('hash-does-not-exist', 'custom/unknown-plugin');
    }

    public function testGetEntityWithAutoPluginResolutionThrowsNotFound(): void
    {
        $Processes = new Processes();

        try {
            $Processes->getEntity('definitely-unknown-hash-' . uniqid(), false);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $Exception) {
            $this->assertSame(404, $Exception->getCode());
        }
    }

    public function testGetListReturnsArray(): void
    {
        $originalPackageManager = QUI::$PackageManager;
        $Manager = $this->createMock(Manager::class);
        $Manager->method('isInstalled')->willReturn(false);
        QUI::$PackageManager = $Manager;

        try {
            $result = (new Processes())->getList();
        } finally {
            QUI::$PackageManager = $originalPackageManager;
        }

        $this->assertSame([], $result);
    }

    public function testGetListCatchesDbalExceptionsAndContinues(): void
    {
        $Processes = new class ($this->Connection, $this->testTableName('missing')) extends Processes {
            public function __construct(
                private Connection $Connection,
                private string $missingTable
            ) {
            }

            protected function readBooking(): void
            {
                $this->Connection->executeQuery(
                    'SELECT * FROM ' . $this->Connection->quoteIdentifier($this->missingTable)
                );
            }

            protected function readInvoices(): void
            {
            }

            protected function readOffers(): void
            {
            }

            protected function readOrders(): void
            {
            }

            protected function readPurchasing(): void
            {
            }

            protected function readSalesOrders(): void
            {
            }

            protected function readTransactions(): void
            {
            }
        };

        $this->assertSame([], $Processes->getList());
    }

    public function testGetListCombinesAndSortsPersistedEntities(): void
    {
        $originalTables = $this->configureUniqueProcessTables();

        $this->createProcessTables();
        $this->Connection->insert(InvoiceHandler::$invoiceTable, [
            'hash' => 'invoice-shared',
            'global_process_id' => 'shared-process',
            'date' => '2026-02-05 12:00:00'
        ]);
        $this->Connection->insert(OffersHandler::$offersTable, [
            'hash' => 'offer-shared',
            'global_process_id' => 'shared-process',
            'date' => '2026-02-01 09:00:00'
        ]);
        $this->Connection->insert(OrderHandler::$orderTable, [
            'hash' => 'order-own-process',
            'global_process_id' => '',
            'c_date' => '2026-03-10 10:00:00'
        ]);
        $this->Connection->insert(SalesOrdersHandler::$salesOrdersTable, [
            'hash' => 'sales-shared',
            'global_process_id' => 'shared-process',
            'date' => '2026-02-03 11:00:00'
        ]);
        $this->Connection->insert(TransactionFactory::$transactionsTable, [
            'hash' => 'transaction-shared',
            'global_process_id' => 'shared-process',
            'date' => '2026-02-04 08:00:00'
        ]);

        $originalPackageManager = QUI::$PackageManager;
        $Manager = $this->createMock(Manager::class);
        $Manager->method('isInstalled')->willReturnCallback(
            static fn(string $package): bool => in_array($package, [
                'quiqqer/invoice',
                'quiqqer/offers',
                'quiqqer/order',
                'quiqqer/salesorders',
                'quiqqer/payment-transactions'
            ], true)
        );
        QUI::$PackageManager = $Manager;

        try {
            $Processes = new class ($this->Connection) extends Processes {
                public function __construct(private Connection $Connection)
                {
                }

                protected function getDatabaseConnection(): Connection
                {
                    return $this->Connection;
                }
            };

            $result = $Processes->getList();
        } finally {
            QUI::$PackageManager = $originalPackageManager;
            $this->restoreProcessTables($originalTables);
        }

        self::assertSame(['order-own-process', 'shared-process'], array_keys($result));
        self::assertSame([
            'date' => '2026-02-01 09:00:00',
            'invoice' => 'invoice-shared',
            'offer' => 'offer-shared',
            'salesorders' => 'sales-shared',
            'transactions' => 'transaction-shared'
        ], $result['shared-process']);
        self::assertSame([
            'date' => '2026-03-10 10:00:00',
            'order' => 'order-own-process'
        ], $result['order-own-process']);
    }

    private function createProcessTables(): void
    {
        foreach (
            [
            InvoiceHandler::$invoiceTable => 'date',
            OffersHandler::$offersTable => 'date',
            OrderHandler::$orderTable => 'c_date',
            SalesOrdersHandler::$salesOrdersTable => 'date',
            TransactionFactory::$transactionsTable => 'date'
            ] as $tableName => $dateColumn
        ) {
            $Table = new Table($tableName);
            $Table->addColumn('hash', Types::STRING, ['length' => 250]);
            $Table->addColumn('global_process_id', Types::STRING, ['length' => 250]);
            $Table->addColumn($dateColumn, Types::STRING, ['length' => 50]);
            $this->createTestTable($Table);
        }
    }

    /** @return array<string, string> */
    private function configureUniqueProcessTables(): array
    {
        $originalTables = [
            'invoice' => InvoiceHandler::$invoiceTable,
            'offers' => OffersHandler::$offersTable,
            'order' => OrderHandler::$orderTable,
            'salesOrders' => SalesOrdersHandler::$salesOrdersTable,
            'transactions' => TransactionFactory::$transactionsTable
        ];

        InvoiceHandler::$invoiceTable = $this->testTableName('invoices');
        OffersHandler::$offersTable = $this->testTableName('offers');
        OrderHandler::$orderTable = $this->testTableName('orders');
        SalesOrdersHandler::$salesOrdersTable = $this->testTableName('sales_orders');
        TransactionFactory::$transactionsTable = $this->testTableName('transactions');

        return $originalTables;
    }

    /** @param array<string, string> $tables */
    private function restoreProcessTables(array $tables): void
    {
        InvoiceHandler::$invoiceTable = $tables['invoice'];
        OffersHandler::$offersTable = $tables['offers'];
        OrderHandler::$orderTable = $tables['order'];
        SalesOrdersHandler::$salesOrdersTable = $tables['salesOrders'];
        TransactionFactory::$transactionsTable = $tables['transactions'];
    }
}
