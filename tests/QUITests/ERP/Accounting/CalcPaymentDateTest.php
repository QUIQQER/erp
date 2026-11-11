<?php

namespace QUITests\ERP\Accounting;

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Constants;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUITests\ERP\DatabaseTestCase;
use ReflectionProperty;

require_once __DIR__ . '/Fixtures/PaymentCalculationEntityInterface.php';

class CalcPaymentDateTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach ([Invoice::class, InvoiceTemporary::class] as $className) {
            if (!class_exists($className, false)) {
                $stubPath = str_replace('\\', '/', $className) . '.php';
                require_once dirname(__DIR__, 3) . '/stubs/' . $stubPath;
            }
        }
    }

    /** @return array<string, array{class-string, int}> */
    public static function paymentEntities(): array
    {
        return [
            'posted invoice updates its own date' => [Invoice::class, 1700000000],
            'draft leaves posted invoice with same id unchanged' => [InvoiceTemporary::class, 123],
            'other ERP entity leaves posted invoice unchanged' => [PaymentCalculationEntityInterface::class, 123]
        ];
    }

    /** @dataProvider paymentEntities */
    public function testOnlyPostedInvoiceUpdatesPersistedInvoiceDate(string $entityClass, int $expectedDate): void
    {
        $invoiceTable = $this->testTableName('invoice');
        $Table = new Table($invoiceTable);
        $Table->addColumn('id', Types::INTEGER);
        $Table->addColumn('paid_date', Types::INTEGER);
        $Table->setPrimaryKey(['id']);
        $this->createTestTable($Table);
        $this->Connection->insert($invoiceTable, ['id' => 42, 'paid_date' => 123]);

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Currency->method('getPrecision')->willReturn(2);
        $Currency->method('amount')->willReturnCallback(
            static fn(float | int $amount): float => round((float)$amount, 2)
        );
        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('isComplete')->willReturn(true);
        $Transaction->method('getAmount')->willReturn(11.78);
        $Transaction->method('getCurrency')->willReturn($Currency);
        $Transaction->method('getDate')->willReturn('1700000000');
        $Transaction->method('getTxId')->willReturn('paid-date-regression');

        $Transactions = $this->createMock(TransactionHandler::class);
        $Transactions->method('getTransactionsByHash')->with('paid-document')->willReturn([$Transaction]);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('getCalculations')->willReturn(['sum' => 11.78]);

        $attributes = [
            'paid_status' => Constants::PAYMENT_STATUS_OPEN,
            'paid_date' => 0
        ];
        $Entity = $this->createMock($entityClass);
        $Entity->method('getHash')->willReturn('paid-document');
        $Entity->method('getCleanId')->willReturn(42);
        $Entity->method('getCurrency')->willReturn($Currency);
        $Entity->method('getArticles')->willReturn($Articles);
        $Entity->method('getAttribute')->willReturnCallback(
            static function (string $key) use (&$attributes): mixed {
                return $attributes[$key] ?? null;
            }
        );
        $Entity->method('setAttribute')->willReturnCallback(
            static function (string $key, mixed $value) use (&$attributes): void {
                $attributes[$key] = $value;
            }
        );

        $ConnectionProperty = new ReflectionProperty(QUI::class, 'QueryBuilder');
        $originalConnection = $ConnectionProperty->getValue();
        $Singletons = new ReflectionProperty(QUI\Utils\Singleton::class, 'instances');
        $originalSingletons = $Singletons->getValue();
        $InvoiceTable = new ReflectionProperty(InvoiceHandler::class, 'invoiceTable');
        $originalInvoiceTable = $InvoiceTable->getValue();
        $DefaultCurrency = new ReflectionProperty(CurrencyHandler::class, 'Default');
        $originalDefaultCurrency = $DefaultCurrency->getValue();

        try {
            $ConnectionProperty->setValue(null, $this->Connection);
            $singletons = $originalSingletons;
            $singletons[TransactionHandler::class] = $Transactions;
            $Singletons->setValue(null, $singletons);
            $InvoiceTable->setValue(null, $invoiceTable);
            $DefaultCurrency->setValue(null, $Currency);

            $result = Calc::calculatePayments($Entity);

            self::assertSame(Constants::PAYMENT_STATUS_PAID, $result['paidStatus']);
            self::assertSame(1700000000, $result['paidDate']);
            self::assertSame(0.0, $result['toPay']);
            self::assertSame($expectedDate, (int)$this->Connection->fetchOne(
                'SELECT paid_date FROM ' . $this->Connection->quoteIdentifier($invoiceTable) . ' WHERE id = ?',
                [42]
            ));
        } finally {
            $DefaultCurrency->setValue(null, $originalDefaultCurrency);
            $InvoiceTable->setValue(null, $originalInvoiceTable);
            $Singletons->setValue(null, $originalSingletons);
            $ConnectionProperty->setValue(null, $originalConnection);
        }
    }
}
