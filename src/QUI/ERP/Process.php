<?php

/**
 * This file contains QUI\ERP\Process
 */

namespace QUI\ERP;

use QUI;

use function array_filter;
use function array_map;
use function array_merge;
use function class_exists;
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

    /**
     * Return all entities which are connected to this process
     *
     * @return ErpEntityInterface[]
     */
    public function getEntities(): array
    {
        $entities = array_merge(
            $this->getInvoices(),
            $this->getOrders(),
            $this->getOffers(),
            $this->getBookings(),
            $this->getPurchasing(),
            $this->getSalesOrders()
        );

        return array_filter($entities);
    }

    /**
     * This method is designed to organize and group ERP transaction entities that implement the ErpTransactionsInterface.
     * It filters entities such as invoices, invoice drafts, orders,
     * and other related items to identify and group together entities that are interconnected.
     *
     * For instance, if an order generates an invoice, this method will group them together.
     * The primary function is to categorize these ERP entities based on their relationships and associations,
     * facilitating easier management and retrieval of related transactional data within the ERP system.
     *
     * @return array
     */
    public function getGroupedRelatedTransactionEntities(): array
    {
        $entities = $this->getEntities();
        $entities = array_filter($entities, function ($obj) {
            return $obj instanceof ErpTransactionsInterface;
        });

        // invoices into the groups
        if (
            class_exists('QUI\ERP\Accounting\Invoice\Invoice')
            && class_exists('QUI\ERP\Accounting\Invoice\InvoiceTemporary')
        ) {
            foreach ($entities as $Entity) {
                if (
                    !($Entity instanceof QUI\ERP\Accounting\Invoice\Invoice
                        || $Entity instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary)
                ) {
                    continue;
                }

                $uuid = $Entity->getUUID();

                $groups[$uuid][] = $Entity;

                if (class_exists('QUI\ERP\Order\Handler') && $Entity->getAttribute('order_id')) {
                    try {
                        $groups[$uuid][] = QUI\ERP\Order\Handler::getInstance()->get(
                            $Entity->getAttribute('order_id')
                        );
                    } catch (QUI\Exception) {
                    }
                }

                if (class_exists('QUI\ERP\SalesOrders\SalesOrder')) {
                    $salesOrder = $Entity->getPaymentData('salesOrder');

                    if ($salesOrder) {
                        try {
                            $groups[$uuid][] = QUI\ERP\SalesOrders\Handler::getSalesOrder($salesOrder['hash']);
                        } catch (QUI\Exception) {
                        }
                    }
                }
            }
        }

        // not group
        $notGroup = [];
        $isInGroups = function (ErpEntityInterface $Entity) use ($groups) {
            foreach ($groups as $group) {
                foreach ($group as $EntityInstance) {
                    if ($Entity->getUUID() === $EntityInstance->getUUID()) {
                        return true;
                    }
                }
            }

            return false;
        };

        foreach ($entities as $Entity) {
            if (!$isInGroups($Entity)) {
                $notGroup[] = $Entity;
            }
        }

        // resulting
        $entitiesArray = array_map(function ($Entity) {
            return $Entity->toArray();
        }, $entities);

        $notGroup = array_map(function ($Entity) {
            return $Entity->toArray();
        }, $notGroup);

        $grouped = [];

        foreach ($groups as $key => $entries) {
            foreach ($entries as $entity) {
                $grouped[$key][] = $entity->toArray();
            }
        }

        return [
            'entities' => $entitiesArray,
            'grouped' => $grouped,
            'notGroup' => $notGroup
        ];
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
        $this->parseTransactions($History);

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
                    'hash' => $Invoice->getUUID()
                ]),
                strtotime($Invoice->getAttribute('date')),
                'quiqqer/invoice',
                'fa fa-file-text-o',
                false,
                $Invoice->getUUID()
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
                    $Invoice->getUUID()
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
        if (!QUI::getPackageManager()->isInstalled('quiqqer/invoice')) {
            return [];
        }

        try {
            return QUI\ERP\Accounting\Invoice\Handler::getInstance()->getInvoicesByGlobalProcessId($this->processId);
        } catch (\QUI\Exception) {
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
            $history = $Order->getHistory()->toArray();
            $hasCreateMessage = false;
            $createMessage = QUI::getLocale()->get('quiqqer/erp', 'process.history.order.created', [
                'hash' => $Order->getUUID()
            ]);

            foreach ($history as $entry) {
                if ($entry['message'] === $createMessage) {
                    $hasCreateMessage = true;
                    break;
                }
            }

            if ($hasCreateMessage === false) {
                $History->addComment(
                    $createMessage,
                    strtotime($Order->getCreateDate()),
                    'quiqqer/order',
                    'fa fa-shopping-basket'
                );
            }

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
                    $Order->getUUID()
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
        if (!QUI::getPackageManager()->isInstalled('quiqqer/order')) {
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
     * @return array
     */
    public function getOrders(): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/order')) {
            return [];
        }

        try {
            return QUI\ERP\Order\Handler::getInstance()->getOrdersByGlobalProcessId($this->processId);
        } catch (QUI\Exception) {
            return [];
        }
    }

    //endregion

    //region offers
    protected function parseOffers(Comments $History): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/offers')) {
            return;
        }

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
                'from' => QUI\ERP\Accounting\Offers\Handler::getInstance()->offersTable(),
                'where_or' => [
                    'global_process_id' => $this->processId,
                    'hash' => $this->processId
                ]
            ]);
        } catch (\Exception) {
            return [];
        }

        $result = [];
        $Offers = QUI\ERP\Accounting\Offers\Handler::getInstance();

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

        if (!class_exists('QUI\ERP\Booking\Repository\BookingRepository')) {
            return [];
        }

        if (!class_exists('QUI\ERP\Booking\Table')) {
            return [];
        }

        try {
            $bookings = QUI::getDatabase()->fetch([
                'select' => 'uuid,globalProcessId,createDate',
                'from' => QUI\ERP\Booking\Table::BOOKINGS->tableName(),
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
     * @return array
     */
    public function getPurchasing(): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/purchasing')) {
            return [];
        }

        if (!class_exists('QUI\ERP\Purchasing\Processes\PurchasingProcess')) {
            return [];
        }

        if (!class_exists('QUI\ERP\Purchasing\Processes\Handler')) {
            return [];
        }

        try {
            $purchasing = QUI::getDatabase()->fetch([
                'select' => 'id,hash,global_process_id,date',
                'from' => QUI\ERP\Purchasing\Processes\Handler::getTablePurchasingProcesses(),
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
                $result[] = QUI\ERP\Purchasing\Processes\Handler::getPurchasingProcess($process['id']);
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
     * @return array
     */
    public function getSalesOrders(): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/salesorders')) {
            return [];
        }

        try {
            $salesOrders = QUI::getDatabase()->fetch([
                'select' => 'id,hash,global_process_id,date',
                'from' => QUI\ERP\SalesOrders\Handler::getTableSalesOrders(),
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
                $result[] = QUI\ERP\SalesOrders\Handler::getSalesOrder($salesOrder['id']);
            } catch (\Exception) {
            }
        }

        return $result;
    }

    //endregion

    //region transactions
    protected function parseTransactions(Comments $History): void
    {
        // orders
        $transactions = $this->getTransactions();

        foreach ($transactions as $Transaction) {
            $History->addComment(
                QUI::getLocale()->get('quiqqer/erp', 'process.history.transaction.created', [
                    'hash' => $Transaction->getHash(),
                    'amount' => $Transaction->getAmountFormatted()
                ]),
                strtotime($Transaction->getDate()),
                'quiqqer/payment-transaction',
                'fa fa-money',
                false,
                $Transaction->getHash()
            );
        }
    }

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
        if (!QUI::getPackageManager()->isInstalled('quiqqer/payment-transactions')) {
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
