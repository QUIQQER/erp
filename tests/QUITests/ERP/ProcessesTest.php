<?php

namespace QUITests\ERP;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Processes;
use QUI\Exception;
use QUI\Package\Manager;
use ReflectionClass;

class ProcessesTest extends TestCase
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
        $Processes = new class () extends Processes {
            protected function readBooking(): void
            {
                DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])
                    ->executeQuery('SELECT * FROM missing_table');
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

    public function testGetListCombinesAndSortsPersistedEntitiesFromSQLite(): void
    {
        $Connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);

        $this->createProcessTables($Connection);
        $Connection->insert('processes_invoice_test', [
            'hash' => 'invoice-shared',
            'global_process_id' => 'shared-process',
            'date' => '2026-02-05 12:00:00'
        ]);
        $Connection->insert('processes_offers_test', [
            'hash' => 'offer-shared',
            'global_process_id' => 'shared-process',
            'date' => '2026-02-01 09:00:00'
        ]);
        $Connection->insert('processes_order_test', [
            'hash' => 'order-own-process',
            'global_process_id' => '',
            'c_date' => '2026-03-10 10:00:00'
        ]);
        $Connection->insert('processes_sales_orders_test', [
            'hash' => 'sales-shared',
            'global_process_id' => 'shared-process',
            'date' => '2026-02-03 11:00:00'
        ]);
        $Connection->insert('processes_transactions_test', [
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
            $Processes = new class ($Connection) extends Processes {
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
            $Connection->close();
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

    private function createProcessTables(Connection $Connection): void
    {
        $Schema = $Connection->createSchemaManager();

        foreach (
            [
            'processes_invoice_test' => 'date',
            'processes_offers_test' => 'date',
            'processes_order_test' => 'c_date',
            'processes_sales_orders_test' => 'date',
            'processes_transactions_test' => 'date'
            ] as $tableName => $dateColumn
        ) {
            $Table = new \Doctrine\DBAL\Schema\Table($tableName);
            $Table->addColumn('hash', 'string');
            $Table->addColumn('global_process_id', 'string');
            $Table->addColumn($dateColumn, 'string');
            $Schema->createTable($Table);
        }
    }
}
