<?php

namespace QUITests\ERP;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\ErpTransactionsInterface;
use QUI\ERP\Process;
use QUI\Package\Manager;
use ReflectionProperty;

class ProcessTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (
            [
            'QUI/ERP/Accounting/Invoice/Invoice.php',
            'QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
            'QUI/ERP/Accounting/Invoice/Handler.php',
            'QUI/ERP/Accounting/Offers/Offer.php',
            'QUI/ERP/Accounting/Offers/Handler.php',
            'QUI/ERP/Order/AbstractOrder.php',
            'QUI/ERP/Order/Order.php',
            'QUI/ERP/Order/OrderInProcess.php',
            'QUI/ERP/Order/Handler.php',
            'QUI/ERP/SalesOrders/SalesOrder.php',
            'QUI/ERP/SalesOrders/Handler.php',
            'QUI/ERP/Accounting/Payments/Transactions/Handler.php'
            ] as $stubFile
        ) {
            $className = str_replace(['/', '.php'], ['\\', ''], $stubFile);

            if (!class_exists($className, false)) {
                require_once dirname(__DIR__, 2) . '/stubs/' . $stubFile;
            }
        }
    }

    public function testGetUuidReturnsConstructorProcessId(): void
    {
        $Process = new Process('erp-process-123');

        $this->assertSame('erp-process-123', $Process->getUUID());
    }

    public function testActiveDateConstant(): void
    {
        $this->assertSame('2024-08-01 00:00:00', Process::PROCESS_ACTIVE_DATE);
    }

    public function testUnavailableOptionalPackagesProduceAnEmptyProcessContract(): void
    {
        $originalPackageManager = QUI::$PackageManager;
        $Manager = $this->createMock(Manager::class);
        $Manager->method('isInstalled')->willReturn(false);
        QUI::$PackageManager = $Manager;

        try {
            $Process = new Process('without-optional-packages');

            self::assertSame([], $Process->getEntities());
            self::assertFalse($Process->hasInvoice());
            self::assertFalse($Process->hasTemporaryInvoice());
            self::assertSame([], $Process->getInvoices());
            self::assertFalse($Process->hasOrder());
            self::assertNull($Process->getOrder());
            self::assertSame([], $Process->getOrders());
            self::assertSame([], $Process->getOffers());
            self::assertSame([], $Process->getBookings());
            self::assertSame([], $Process->getPurchasing());
            self::assertSame([], $Process->getSalesOrders());
            self::assertFalse($Process->hasTransactions());
            self::assertSame([], $Process->getTransactions());
        } finally {
            QUI::$PackageManager = $originalPackageManager;
        }
    }

    public function testInvoiceTypeChecksAndCachedTransactionsAreObservable(): void
    {
        $Invoice = $this->createMock(QUI\ERP\Accounting\Invoice\Invoice::class);
        $Temporary = $this->createMock(QUI\ERP\Accounting\Invoice\InvoiceTemporary::class);
        $Process = new class ('typed-entities', [$Invoice, $Temporary]) extends Process {
            /** @param array<mixed> $invoices */
            public function __construct(string $uuid, private array $invoices)
            {
                parent::__construct($uuid);
            }

            public function getInvoices(): array
            {
                return $this->invoices;
            }
        };

        self::assertTrue($Process->hasInvoice());
        self::assertTrue($Process->hasTemporaryInvoice());

        $originalPackageManager = QUI::$PackageManager;
        $Manager = $this->createMock(Manager::class);
        $Manager->method('isInstalled')->with('quiqqer/payment-transactions')->willReturn(true);
        QUI::$PackageManager = $Manager;

        try {
            (new ReflectionProperty(Process::class, 'transactions'))->setValue($Process, ['transaction']);
            self::assertTrue($Process->hasTransactions());
            self::assertSame(['transaction'], $Process->getTransactions());
        } finally {
            QUI::$PackageManager = $originalPackageManager;
        }
    }

    public function testRelatedTransactionGroupingSeparatesInvoicesAndUngroupedEntities(): void
    {
        $Invoice = $this->createMock(QUI\ERP\Accounting\Invoice\Invoice::class);
        $Invoice->method('getUUID')->willReturn('grouped-invoice');
        $Invoice->method('getAttribute')->with('order_id')->willReturn(null);
        $Invoice->method('getPaymentData')->with('salesOrder')->willReturn([]);
        $Invoice->method('toArray')->willReturn([
            'uuid' => 'grouped-invoice',
            'type' => 'invoice'
        ]);

        $Standalone = $this->createMockForIntersectionOfInterfaces([
            ErpEntityInterface::class,
            ErpTransactionsInterface::class
        ]);
        $Standalone->method('getUUID')->willReturn('standalone-entity');
        $Standalone->method('toArray')->willReturn([
            'uuid' => 'standalone-entity',
            'type' => 'custom'
        ]);

        $Process = new class ('grouped-process', [$Invoice, $Standalone]) extends Process {
            /** @param array<mixed> $entities */
            public function __construct(string $uuid, private array $entities)
            {
                parent::__construct($uuid);
            }

            public function getEntities(): array
            {
                return $this->entities;
            }
        };

        $result = $Process->getGroupedRelatedTransactionEntities();

        self::assertSame([
            'uuid' => 'grouped-invoice',
            'type' => 'invoice'
        ], $result['grouped']['grouped-invoice'][0]);
        self::assertSame([[
            'uuid' => 'standalone-entity',
            'type' => 'custom'
        ]], $result['notGroup']);
        self::assertCount(2, $result['entities']);

        $filtered = $Process->getGroupedRelatedTransactionEntities(
            static fn(ErpEntityInterface $Entity): bool => $Entity->getUUID() === 'standalone-entity'
        );
        self::assertSame([], $filtered['grouped']);
        self::assertSame([[
            'uuid' => 'standalone-entity',
            'type' => 'custom'
        ]], $filtered['notGroup']);
    }

    public function testInstalledOptionalHandlersReturnSafeObservableFallbacks(): void
    {
        $Connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);
        $this->createEntityLookupTables($Connection);

        foreach (['processes_offers_test', 'processes_temporary_offers_test'] as $table) {
            $Connection->insert($table, [
                'id' => 1,
                'hash' => 'optional-process',
                'global_process_id' => 'optional-process',
                'date' => '2026-08-01 10:00:00'
            ]);
        }

        foreach (['processes_sales_orders_test', 'processes_sales_order_drafts_test'] as $table) {
            $Connection->insert($table, [
                'id' => 1,
                'hash' => 'optional-process',
                'global_process_id' => 'optional-process',
                'date' => '2026-08-01 10:00:00'
            ]);
        }

        $originalPackageManager = QUI::$PackageManager;
        $Manager = $this->createMock(Manager::class);
        $Manager->method('isInstalled')->willReturn(true);
        QUI::$PackageManager = $Manager;

        try {
            $Process = new class ('optional-process', $Connection) extends Process {
                public function __construct(string $processId, private Connection $Connection)
                {
                    parent::__construct($processId);
                }

                protected function getDatabaseConnection(): Connection
                {
                    return $this->Connection;
                }
            };

            self::assertSame([], $Process->getInvoices());
            self::assertNull($Process->getOrder());
            self::assertSame([], $Process->getOrders());
            self::assertSame([], $Process->getOffers());
            self::assertSame([], $Process->getBookings());
            self::assertSame([], $Process->getPurchasing());
            self::assertSame([], $Process->getSalesOrders());
            self::assertSame([], $Process->getTransactions());
            self::assertFalse($Process->hasTransactions());
        } finally {
            QUI::$PackageManager = $originalPackageManager;
            $Connection->close();
        }
    }

    public function testGroupingKeepsPrimaryEntitiesWhenRelatedLookupsFail(): void
    {
        $Invoice = $this->createMock(QUI\ERP\Accounting\Invoice\Invoice::class);
        $Invoice->method('getUUID')->willReturn('invoice-with-links');
        $Invoice->method('getAttribute')->with('order_id')->willReturn(99);
        $Invoice->method('getPaymentData')->with('salesOrder')->willReturn(['hash' => 'missing-sales']);
        $Invoice->method('toArray')->willReturn(['uuid' => 'invoice-with-links']);

        $Order = $this->createMock(QUI\ERP\Order\Order::class);
        $Order->method('getUUID')->willReturn('order-with-link');
        $Order->method('getPaymentDataEntry')->with('salesOrder')->willReturn([]);
        $Order->method('getCustomDataEntry')->with('salesOrder')->willReturn(['hash' => 'missing-sales']);
        $Order->method('toArray')->willReturn(['uuid' => 'order-with-link']);

        $SalesOrder = $this->createMock(QUI\ERP\SalesOrders\SalesOrder::class);
        $SalesOrder->method('getUUID')->willReturn('sales-order-only');
        $SalesOrder->method('toArray')->willReturn(['uuid' => 'sales-order-only']);

        foreach ([$Invoice, $Order, $SalesOrder] as $Entity) {
            $Process = new class ('grouping-fallback', $Entity) extends Process {
                public function __construct(string $uuid, private ErpEntityInterface $Entity)
                {
                    parent::__construct($uuid);
                }

                public function getEntities(): array
                {
                    return [$this->Entity];
                }
            };

            $result = $Process->getGroupedRelatedTransactionEntities();
            self::assertSame($Entity->getUUID(), array_key_first($result['grouped']));
            self::assertSame([], $result['notGroup']);
        }
    }

    private function createEntityLookupTables(Connection $Connection): void
    {
        $Schema = $Connection->createSchemaManager();

        foreach (
            [
            'processes_offers_test',
            'processes_temporary_offers_test',
            'processes_sales_orders_test',
            'processes_sales_order_drafts_test'
            ] as $tableName
        ) {
            $Table = new \Doctrine\DBAL\Schema\Table($tableName);
            $Table->addColumn('id', 'integer');
            $Table->addColumn('hash', 'string');
            $Table->addColumn('global_process_id', 'string');
            $Table->addColumn('date', 'string');
            $Schema->createTable($Table);
        }
    }
}
