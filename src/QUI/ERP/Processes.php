<?php

/**
 * This file contains QUI\ERP\Processes
 */

namespace QUI\ERP;

use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Offers\Handler as OfferHandler;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\SalesOrders\Handler as SalesOrdersHandler;
use QUI\Exception;

use function class_exists;

/**
 *
 * Reads following modules for global process id entities
 * - quiqqer/invoice
 * - quiqqer/order
 * - quiqqer/offer
 * - quiqqer/salesorders
 * - quiqqer/purchasing
 * - quiqqer/booking
 * - quiqqer/contracts
 * - quiqqer/payments
 * - quiqqer/payment-transactions
 * - quiqqer/delivery-notes
 */
class Processes
{
    protected array $list = [];

    /**
     * @throws Exception
     */
    public function getEntity($entityHash): ErpEntityInterface
    {
        //'quiqqer/booking',
        try {
        } catch (\Exception) {
        }

        //'quiqqer/contracts',
        try {
        } catch (\Exception) {
        }

        //'quiqqer/delivery-notes',
        try {
        } catch (\Exception) {
        }

        //'quiqqer/invoice',
        try {
            return QUI\ERP\Accounting\Invoice\Handler::getInstance()->getInvoiceByHash($entityHash);
        } catch (\Exception) {
        }

        //'quiqqer/offers',
        try {
            return QUI\ERP\Accounting\Offers\Handler::getInstance()->getOfferByHash($entityHash);
        } catch (\Exception) {
        }

        //'quiqqer/order',
        try {
            return QUI\ERP\Order\Handler::getInstance()->getOrderByHash($entityHash);
        } catch (\Exception) {
        }

        //'quiqqer/purchasing',
        try {
        } catch (\Exception) {
        }

        //'quiqqer/salesorders'
        try {
            return QUI\ERP\SalesOrders\Handler::getSalesOrderByHash($entityHash);
        } catch (\Exception) {
        }

        throw new Exception(
            [
                'quiqqer/erp',
                'exception.entity.not.found',
                [
                    'hash' => $entityHash
                ]
            ],
            404
        );
    }

    /**
     * @return array
     */
    public function getList(): array
    {
        try {
            $this->readBooking();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readInvoices();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readOffers();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readOrders();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readPurchasing();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readSalesOrders();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        try {
            $this->readTransactions();
        } catch (QUI\Database\Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }

        uasort($this->list, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $this->list;
    }

    /**
     * Returns the plugin list with which plugins the processes can handle
     *
     * @return string[]
     */
    public function getWantedPluginList(): array
    {
        return [
            'quiqqer/booking',
            'quiqqer/contracts',
            'quiqqer/delivery-notes',
            'quiqqer/invoice',
            'quiqqer/offers',
            'quiqqer/order',
            'quiqqer/payments',
            'quiqqer/payment-transactions',
            'quiqqer/purchasing',
            'quiqqer/salesorders'
        ];
    }

    //region read databases

    /**
     * Rads all invoices
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readInvoices(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/invoice')) {
            return;
        }

        $invoices = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => InvoiceHandler::getInstance()->invoiceTable()
        ]);

        foreach ($invoices as $invoice) {
            $gpi = $invoice['global_process_id'];

            if (empty($gpi)) {
                $gpi = $invoice['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $invoice['date']
            );

            $this->list[$gpi]['invoice'] = $invoice['hash'];
        }
    }

    /**
     * Rads all invoices
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readOrders(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/order')) {
            return;
        }

        $orders = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,c_date',
            'from' => OrderHandler::getInstance()->table()
        ]);

        foreach ($orders as $order) {
            $gpi = $order['global_process_id'];

            if (empty($gpi)) {
                $gpi = $order['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $order['c_date']
            );

            $this->list[$gpi]['order'] = $order['hash'];
        }
    }

    /**
     * Read all offers
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readOffers(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/offers')) {
            return;
        }

        $offers = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => OfferHandler::getInstance()->offersTable()
        ]);

        foreach ($offers as $offer) {
            $gpi = $offer['global_process_id'];

            if (empty($gpi)) {
                $gpi = $offer['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $offer['date']
            );

            $this->list[$gpi]['offer'] = $offer['hash'];
        }
    }

    /**
     * Read all sales orders
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readSalesOrders(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/salesorders')) {
            return;
        }

        $salesOrders = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => SalesOrdersHandler::getTableSalesOrders()
        ]);

        foreach ($salesOrders as $salesOrder) {
            $gpi = $salesOrder['global_process_id'];

            if (empty($gpi)) {
                $gpi = $salesOrder['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $salesOrder['date']
            );

            $this->list[$gpi]['salesorders'] = $salesOrder['hash'];
        }
    }

    /**
     * Read all sales orders
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readTransactions(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/payment-transactions')) {
            return;
        }

        $transactions = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => TransactionFactory::table()
        ]);

        foreach ($transactions as $transaction) {
            $gpi = $transaction['global_process_id'];

            if (empty($gpi)) {
                $gpi = $transaction['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $transaction['date']
            );

            $this->list[$gpi]['transactions'] = $transaction['hash'];
        }
    }

    /**
     * Read all purchases
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readPurchasing(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/purchasing')) {
            return;
        }

        if (!class_exists('QUI\ERP\Purchasing\Processes\Handler')) {
            return;
        }

        $purchasing = QUI::getDatabase()->fetch([
            'select' => 'hash,global_process_id,date',
            'from' => QUI\ERP\Purchasing\Processes\Handler::getTablePurchasingProcesses()
        ]);

        foreach ($purchasing as $entry) {
            $gpi = $entry['global_process_id'];

            if (empty($gpi)) {
                $gpi = $entry['hash'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $entry['date']
            );

            $this->list[$gpi]['purchasing'] = $entry['hash'];
        }
    }

    /**
     * Read all purchases
     *
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function readBooking(): void
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/booking')) {
            return;
        }

        if (!class_exists('QUI\ERP\Booking\Table')) {
            return;
        }

        $bookings = QUI::getDatabase()->fetch([
            'select' => 'uuid,globalProcessId,createDate',
            'from' => QUI\ERP\Booking\Table::BOOKINGS->tableName(),
        ]);

        foreach ($bookings as $booking) {
            $gpi = $booking['globalProcessId'];

            if (empty($gpi)) {
                $gpi = $booking['uuid'];
            }

            if (!isset($this->list[$gpi])) {
                $this->list[$gpi] = [];
            }

            $this->list[$gpi]['date'] = $this->getEarlierDate(
                $this->list[$gpi]['date'] ?? null,
                $booking['createDate']
            );

            $this->list[$gpi]['booking'] = $booking['uuid'];
        }
    }

    //endregion

    //region utils

    protected function getEarlierDate($date1, $date2)
    {
        $timestamp1 = strtotime($date1);
        $timestamp2 = strtotime($date2);

        if ($date1 === null && $date2) {
            return $date2;
        }

        if ($date1 && $date2 === null) {
            return $date1;
        }

        return ($timestamp1 < $timestamp2) ? $date1 : $date2;
    }

    //endregion
}
