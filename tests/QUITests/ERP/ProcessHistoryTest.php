<?php

namespace QUITests\ERP;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use QUI;
use QUI\ERP\Comments;
use QUI\ERP\Process;
use QUI\Package\Manager;

require_once __DIR__ . '/Fixtures/ProcessHistoryEntity.php';
require_once __DIR__ . '/Fixtures/ProcessHistoryFixture.php';
require_once __DIR__ . '/Fixtures/ProcessTransactionFixture.php';

class ProcessHistoryTest extends DatabaseTestCase
{
    private string $historyTable;
    private ?QUI\Events\Manager $originalEvents;
    private ?QUI\Locale $originalLocale;
    private ?Manager $originalPackageManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->historyTable = $this->testTableName('history');
        $Table = new Table($this->historyTable);
        $Table->addColumn('id', Types::STRING, ['length' => 250]);
        $Table->addColumn('history', Types::TEXT, ['notnull' => false]);
        $Table->setPrimaryKey(['id']);
        $this->createTestTable($Table);
        $this->originalEvents = QUI::$Events;
        $this->originalLocale = QUI::$Locale;
        $this->originalPackageManager = QUI::$PackageManager;

        $Events = $this->createMock(QUI\Events\Manager::class);
        $Events->expects(self::exactly(2))->method('fireEvent');
        QUI::$Events = $Events;

        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->method('get')->willReturnCallback(
            static fn(string $package, string $key, mixed $replacements = []): string =>
                $key . (is_array($replacements) && isset($replacements['hash']) ? ':' . $replacements['hash'] : '')
        );
        QUI::$Locale = $Locale;

        $PackageManager = $this->createMock(Manager::class);
        $PackageManager->method('isInstalled')->willReturnCallback(
            static fn(string $package): bool => $package === 'quiqqer/offers'
        );
        QUI::$PackageManager = $PackageManager;
    }

    protected function tearDown(): void
    {
        try {
            QUI::$Events = $this->originalEvents;
            QUI::$Locale = $this->originalLocale;
            QUI::$PackageManager = $this->originalPackageManager;
        } finally {
            parent::tearDown();
        }
    }

    public function testCompleteHistoryCombinesAllEntityTypesAndMetadata(): void
    {
        $Process = new ProcessHistoryFixture('process-history', $this->Connection, $this->historyTable);
        $Process->invoices = [new ProcessHistoryEntity(
            'invoice-1',
            'INV-1',
            '2026-01-01 10:00:00',
            [['message' => 'Invoice changed', 'time' => strtotime('2026-01-02'), 'id' => 'i-change']]
        )];
        $Process->orders = [new ProcessHistoryEntity(
            'order-1',
            'ORD-1',
            '2026-02-01 10:00:00',
            [['message' => 'Order changed', 'time' => strtotime('2026-02-02'), 'id' => 'o-change']]
        )];
        $Process->offers = [new ProcessHistoryEntity(
            'offer-1',
            'OFF-1',
            '2026-03-01 10:00:00',
            [['message' => 'Offer changed', 'time' => strtotime('2026-03-02'), 'id' => 'f-change']]
        )];
        $Process->bookings = [new ProcessHistoryEntity(
            'booking-1',
            'BOOK-1',
            '2026-04-01 10:00:00',
            [['message' => 'Booking changed', 'time' => strtotime('2026-04-02'), 'id' => 'b-change']]
        )];
        $Process->purchasing = [new ProcessHistoryEntity(
            'purchase-1',
            'PUR-1',
            '2026-05-01 10:00:00',
            [['message' => 'Purchase changed', 'time' => strtotime('2026-05-02'), 'id' => 'p-change']]
        )];
        $Process->salesOrders = [new ProcessHistoryEntity(
            'sales-1',
            'SALE-1',
            '2026-06-01 10:00:00',
            [['message' => 'Sales order changed', 'time' => strtotime('2026-06-02'), 'id' => 's-change']]
        )];
        $Process->transactionFixtures = [new ProcessTransactionFixture(
            'transaction-1',
            '49.00 EUR',
            '2026-07-01 10:00:00'
        )];

        $comments = $Process->getCompleteHistory()->toArray();
        $messages = array_column($comments, 'message');

        self::assertContains('process.history.invoice.created:INV-1', $messages);
        self::assertContains('Invoice changed', $messages);
        self::assertContains('process.history.order.created:ORD-1', $messages);
        self::assertContains('process.history.offer.created:offer-1', $messages);
        self::assertContains('process.history.booking.created:BOOK-1', $messages);
        self::assertContains('process.history.purchasing.created:purchase-1', $messages);
        self::assertContains('process.history.salesorders.created:sales-1', $messages);
        self::assertContains('process.history.transaction.created:transaction-1', $messages);

        $invoiceChange = $comments[array_search('Invoice changed', $messages, true)];
        self::assertSame('quiqqer/invoice', $invoiceChange['source']);
        self::assertSame('invoice-1', $invoiceChange['objectHash']);
        self::assertTrue($Process->hasTransactions());
        self::assertSame($Process->transactionFixtures, $Process->getTransactions());
        self::assertCount(6, $Process->getEntities());
    }

    public function testCompleteHistoryFiltersLegacyEntriesAndAddsEmptyInformation(): void
    {
        $this->Connection->insert($this->historyTable, [
            'id' => 'legacy-only',
            'history' => json_encode([[
                'message' => 'Legacy comment',
                'time' => strtotime('2024-01-01'),
                'id' => 'legacy'
            ]], JSON_THROW_ON_ERROR)
        ]);
        $Process = new ProcessHistoryFixture('legacy-only', $this->Connection, $this->historyTable);

        $comments = $Process->getCompleteHistory()->toArray();

        self::assertCount(1, $comments);
        self::assertSame('process.history.empty.info', $comments[0]['message']);
        self::assertSame('quiqqer/erp', $comments[0]['source']);
        self::assertFalse($Process->hasTransactions());
    }
}
