<?php

/**
 * This file contains QUI\ERP\Process
 */

namespace QUI\ERP;

use QUI;
use QUI\ERP\Accounting\Offers\Handler as OfferHandler;
use QUI\ERP\Booking\Table as BookingTable;
use QUI\ERP\Purchasing\Processes\Handler as PurchasingHandler;
use QUI\ERP\SalesOrders\Handler as SalesOrdersHandler;

use function count;
use function strtotime;

/**
 * Class Process
 * - represents a complete erp process
 * - Vorgangsnummer
 *
 * @package QUI\ERP
 */
class Process
{
    /**
     * @var string
     */
    protected string $processId;

    /**
     * @var null|array
     */
    protected ?array $transactions = null;

    /**
     * @var null|QUI\ERP\Comments
     */
    protected ?Comments $History = null;

    /**
     * Process constructor.
     *
     * @param string $processId - the global process id
     */
    public function __construct(string $processId)
    {
        $this->processId = $processId;
    }

    /**
     * Return the db table name
     *
     * @return string
     */
    protected function table(): string
    {
        return QUI::getDBTableName('process');
    }

    //region messages

    /**
     * Add a comment to the history for the complete process
     *
     * @param string $message
     * @param bool|int $time - optional, unix timestamp
     */
    public function addHistory(string $message, bool|int $time = false): void
    {
        $this->getHistory()->addComment($message, $time);

        try {
            QUI::getDataBase()->update(
                $this->table(),
                ['history' => $this->getHistory()->toJSON()],
                ['id' => $this->processId]
            );
        } catch (\QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }
    }

    /**
     * Return the history of the process
     * This history only contains the process history
     *
     * If you want the complete history of all process objects, use getCompleteHistory()
     *
     * @return QUI\ERP\Comments
     */
    public function getHistory(): Comments
    {
        if ($this->History === null) {
            $history = '';

            try {
                $result = QUI::getDataBase()->fetch([
                    'from' => $this->table(),
                    'where' => [
                        'id' => $this->processId
                    ],
                    'limit' => 1
                ]);

                if (isset($result[0]['history'])) {
                    $history = $result[0]['history'];
                } else {
                    QUI::getDataBase()->insert($this->table(), [
                        'id' => $this->processId
                    ]);
                }
            } catch (\QUI\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());
            }

            $this->History = QUI\ERP\Comments::unserialize($history);
        }

        return $this->History;
    }

    /**
     * Return a complete history of all process objects
     * invoices and orders
     *
     * @return Comments
     */
    public function getCompleteHistory(): Comments
    {
        $History = $this->getHistory();

        $this->parseBookings($History);
        $this->parseInvoices($History);
        $this->parseOffers($History);
        $this->parseOrders($History);
        $this->parsePurchasing($History);
        $this->parseSalesOrders($History);

        try {
            QUI::getEvents()->fireEvent('quiqqerErpGetCompleteHistory', [$this, $this->processId]);
        } catch (\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerErpProcessHistory', [$this, $this->processId]);
        } catch (\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        return $History;
    }

    //endregion

    //region invoice

    protected function parseInvoices(Comments $History): void
    {
        $invoices = $this->getInvoices();

        foreach ($invoices as $Invoice) {
            $History->addComment(
                QUI::getLocale()->get('quiqqer/erp', 'process.history.invoice.created', [
                    'hash' => $Invoice->getHash()
                ]),
                strtotime($Invoice->getAttribute('date')),
                'quiqqer/invoice',
                'fa fa-file-text-o',
                false,
                $Invoice->getHash()
            );

            $history = $Invoice->getHistory()->toArray();

            foreach ($history as $entry) {
                if (empty($entry['source'])) {
                    $entry['source'] = 'quiqqer/invoice';
                }

                if (empty($entry['sourceIcon'])) {
                    $entry['sourceIcon'] = 'fa fa-file-text-o';
                }

                $History->addComment(
                    $entry['message'],
                    $entry['time'],
                    $entry['source'],
                    $entry['sourceIcon'],
                    $entry['id'],
                    $Invoice->getHash()
                );
            }
        }
    }

    /**
     * Return if the process has invoices or not
     *
     * @return bool
     */
    public function hasInvoice(): bool
    {
        $invoices = $this->getInvoices();

        foreach ($invoices as $Invoice) {
            if ($Invoice instanceof QUI\ERP\Accounting\Invoice\Invoice) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return if the process has temporary invoices or not
     *
     * @return bool
     */
    public function hasTemporaryInvoice(): bool
    {
        $invoices = $this->getInvoices();

        foreach ($invoices as $Invoice) {
            if ($Invoice instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Accounting\Invoice\Invoice[]|Accounting\Invoice\InvoiceTemporary[]
     */
    public function getInvoices(): array
    {
        try {
            return QUI\ERP\Accounting\Invoice\Handler::getInstance()->getInvoicesByGlobalProcessId($this->processId);
        } catch (\QUI\Exception $Exception) {
            return [];
        }
    }

    //endregion

    //region order

    protected function parseOrders(Comments $History): void
    {
        // orders
        $orders = $this->getOrders();

        foreach ($orders as $Order) {
            /* order macht das schon selbst
            $History->addComment(
                QUI::getLocale()->get('quiqqer/erp', 'process.history.order.created'),
                strtotime($Order->getCreateDate()),
                'quiqqer/order',
                'fa fa-shopping-basket'
            );
            */

            $history = $Order->getHistory()->toArray();

            foreach ($history as $entry) {
                if (empty($entry['source'])) {
                    $entry['source'] = 'quiqqer/order';
                }

                if (empty($entry['sourceIcon'])) {
                    $entry['sourceIcon'] = 'fa fa-shopping-basket';
                }

                $History->addComment(
                    $entry['message'],
                    $entry['time'],
                    $entry['source'],
                    $entry['sourceIcon'],
                    $entry['id'],
                    $Order->getHash()
                );
            }
        }
    }

    /**
     * @return bool
     */
    public function hasOrder(): bool
    {
        return !($this->getOrder() === null);
    }

    /**
     * Return the first order of the process
     *
     * @return null|Order\Order|Order\OrderInProcess
     */
    public function getOrder(): Order\OrderInProcess|Order\Order|null
    {
        try {
            QUI::getPackage('quiqqer/order');
        } catch (QUI\Exception $Exception) {
            return null;
        }

        $OrderHandler = QUI\ERP\Order\Handler::getInstance();

        try {
            return $OrderHandler->getOrderByGlobalProcessId($this->processId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $OrderHandler->getOrderByHash($this->processId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }

    /**
     * Return all orders from the process
     *
     * @return array|Order\Order|Order\Order[]|Order\OrderInProcess[]
     */
    public function getOrders(): array
    {
        try {
            QUI::getPackage('quiqqer/order');

            return QUI\ERP\Order\Handler::getInstance()->getOrdersByGlobalProcessId($this->processId);
        } catch (QUI\Exception $Exception) {
            return [];
        }
    }

    //endregion

    //region offers
    protected function parseOffers(Comments $History): void
    {
        // orders
        $offers = $this->getOffers();

        foreach ($offers as $Offer) {
            $History->addComment(
                QUI::getLocale()->get('quiqqer/erp', 'process.history.offer.created', [
                    'hash' => $Offer->getHash()
                ]),
                strtotime($Offer->getAttribute('date')),
                'quiqqer/offer',
                'fa fa-file-text-o',
                false,
                $Offer->getHash()
            );

            $history = $Offer->getHistory()->toArray();

            foreach ($history as $entry) {
                if (empty($entry['source'])) {
                    $entry['source'] = 'quiqqer/offer';
                }

                if (empty($entry['sourceIcon'])) {
                    $entry['sourceIcon'] = 'fa fa-file-text-o';
                }

                $History->addComment(
                    $entry['message'],
                    $entry['time'],
                    $entry['source'],
                    $entry['sourceIcon'],
                    $entry['id'],
                    $Offer->getHash()
                );
            }
        }
    }

    /**
     * @return QUI\ERP\Accounting\Offers\Offer[]
     */
    public function getOffers(): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/offers')) {
            return [];
        }

        try {
            $offers = QUI::getDatabase()->fetch([
                'select' => 'id,hash,global_process_id,date',
                'from' => OfferHandler::getInstance()->offersTable(),
                'where_or' => [
                    'global_process_id' => $this->processId,
                    'hash' => $this->processId
                ]
            ]);
        } catch (\Exception) {
            return [];
        }

        $result = [];
        $Offers = OfferHandler::getInstance();

        foreach ($offers as $offer) {
            try {
                $result[] = $Offers->getOffer($offer['id']);
            } catch (\Exception) {
            }
        }

        return $result;
    }

    //endregion

    //region booking
    protected function parseBookings(Comments $History): void
    {
        // orders
        $bookings = $this->getBookings();

        foreach ($bookings as $Booking) {
            $History->addComment(
                QUI::getLocale()->get('quiqqer/erp', 'process.history.booking.created', [
                    'hash' => $Booking->getUuid()
                ]),
                $Booking->getCreateDate()->getTimestamp(),
                'quiqqer/booking',
                'fa fa-ticket',
                false,
                $Booking->getUuid()
            );

            $history = $Booking->getHistory()->toArray();

            foreach ($history as $entry) {
                if (empty($entry['source'])) {
                    $entry['source'] = 'quiqqer/booking';
                }

                if (empty($entry['sourceIcon'])) {
                    $entry['sourceIcon'] = 'fa fa-ticket';
                }

                $History->addComment(
                    $entry['message'],
                    $entry['time'],
                    $entry['source'],
                    $entry['sourceIcon'],
                    $entry['id'],
                    $Booking->getUuid()
                );
            }
        }
    }

    /**
     * @return array
     */
    public function getBookings(): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/booking')) {
            return [];
        }

        try {
            $bookings = QUI::getDatabase()->fetch([
                'select' => 'uuid,globalProcessId,createDate',
                'from' => BookingTable::BOOKINGS->tableName(),
                'where_or' => [
                    'globalProcessId' => $this->processId,
                    'uuid' => $this->processId
                ]
            ]);
        } catch (\Exception) {
            return [];
        }

        $result = [];
        $BookingRepository = new QUI\ERP\Booking\Repository\BookingRepository();

        foreach ($bookings as $booking) {
            try {
                $result[] = $BookingRepository->getByUuid($booking['uuid']);
            } catch (\Exception) {
            }
        }

        return $result;
    }

    //endregion

    //region purchase / Einkauf
    protected function parsePurchasing(Comments $History): void
    {
        // orders
        $purchasing = $this->getPurchasing();

        foreach ($purchasing as $Purchasing) {
            $History->addComment(
                QUI::getLocale()->get('quiqqer/erp', 'process.history.purchasing.created', [
                    'hash' => $Purchasing->getHash()
                ]),
                strtotime($Purchasing->getAttribute('c_date')),
                'quiqqer/purchasing',
                'fa fa-cart-arrow-down',
                false,
                $Purchasing->getHash()
            );

            $history = $Purchasing->getHistory()->toArray();

            foreach ($history as $entry) {
                if (empty($entry['source'])) {
                    $entry['source'] = 'quiqqer/purchasing';
                }

                if (empty($entry['sourceIcon'])) {
                    $entry['sourceIcon'] = 'fa fa-cart-arrow-down';
                }

                $History->addComment(
                    $entry['message'],
                    $entry['time'],
                    $entry['source'],
                    $entry['sourceIcon'],
                    $entry['id'],
                    $Purchasing->getHash()
                );
            }
        }
    }

    /**
     * @return QUI\ERP\Purchasing\Processes\PurchasingProcess[]
     */
    public function getPurchasing(): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/purchasing')) {
            return [];
        }

        try {
            $purchasing = QUI::getDatabase()->fetch([
                'select' => 'id,hash,global_process_id,date',
                'from' => PurchasingHandler::getTablePurchasingProcesses(),
                'where_or' => [
                    'global_process_id' => $this->processId,
                    'hash' => $this->processId
                ]
            ]);
        } catch (\Exception) {
            return [];
        }

        $result = [];

        foreach ($purchasing as $process) {
            try {
                $result[] = PurchasingHandler::getPurchasingProcess($process['id']);
            } catch (\Exception) {
            }
        }

        return $result;
    }

    //endregion

    //region sales orders / Aufträge
    protected function parseSalesOrders(Comments $History): void
    {
        // orders
        $salesOrders = $this->getSalesOrders();

        foreach ($salesOrders as $SalesOrder) {
            $History->addComment(
                QUI::getLocale()->get('quiqqer/erp', 'process.history.salesorders.created', [
                    'hash' => $SalesOrder->getHash()
                ]),
                strtotime($SalesOrder->getAttribute('c_date')),
                'quiqqer/salesorders',
                'fa fa-suitcase',
                false,
                $SalesOrder->getHash()
            );

            $history = $SalesOrder->getHistory()->toArray();

            foreach ($history as $entry) {
                if (empty($entry['source'])) {
                    $entry['source'] = 'quiqqer/salesorders';
                }

                if (empty($entry['sourceIcon'])) {
                    $entry['sourceIcon'] = 'fa fa-suitcase';
                }

                $History->addComment(
                    $entry['message'],
                    $entry['time'],
                    $entry['source'],
                    $entry['sourceIcon'],
                    $entry['id'],
                    $SalesOrder->getHash()
                );
            }
        }
    }

    /**
     * @return QUI\ERP\Purchasing\Processes\PurchasingProcess[]
     */
    public function getSalesOrders(): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/salesorders')) {
            return [];
        }

        try {
            $salesOrders = QUI::getDatabase()->fetch([
                'select' => 'id,hash,global_process_id,date',
                'from' => SalesOrdersHandler::getTableSalesOrders(),
                'where_or' => [
                    'global_process_id' => $this->processId,
                    'hash' => $this->processId
                ]
            ]);
        } catch (\Exception) {
            return [];
        }

        $result = [];

        foreach ($salesOrders as $salesOrder) {
            try {
                $result[] = SalesOrdersHandler::getSalesOrder($salesOrder['id']);
            } catch (\Exception) {
            }
        }

        return $result;
    }

    //endregion

    //region transactions

    /**
     * @return bool
     */
    public function hasTransactions(): bool
    {
        $transactions = $this->getTransactions();

        return !!count($transactions);
    }

    /**
     * Return all related transactions
     *
     * @return QUI\ERP\Accounting\Payments\Transactions\Transaction[];
     */
    public function getTransactions(): array
    {
        try {
            QUI::getPackage('quiqqer/payment-transactions');
        } catch (QUI\Exception $Exception) {
            return [];
        }

        if ($this->transactions !== null) {
            return $this->transactions;
        }

        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $this->transactions = $Transactions->getTransactionsByProcessId($this->processId);

        return $this->transactions;
    }

    //endregion
}
